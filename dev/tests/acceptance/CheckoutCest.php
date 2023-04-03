<?php

class CheckoutCest
{
    /**
     * @param Step\Acceptance\Magento $I
     */
    public function dependenciesAreConfigured(Step\Acceptance\Magento $I)
    {
        $I->seeNumRecords(1, 'inventory_source');
        $I->seeInDatabase('core_config_data', ['path' => 'oauth/consumer/enable_integration_as_bearer', 'value' => '1']);
        $I->seeInDatabase('core_config_data', ['path' => 'checkout/options/guest_checkout', 'value' => '1']);
        $I->seeInDatabase('core_config_data', ['path' => 'payment/checkmo/active', 'value' => '1']);
        $I->seeInDatabase('oauth_token', ['token' => Step\Acceptance\Magento::ACCESS_TOKEN]);
        $I->deleteFromDatabase('inventory_reservation');
    }

    /**
     * @depends dependenciesAreConfigured
     * @param Step\Acceptance\Magento $I
     */
    public function noInventoryIsReservedAndStockHasBeenDeducted(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_no_res_and_deduct', 10);

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_no_res_and_deduct', 1);
        $I->completeGuestCheckout($cartId);

        //Prevent all writes to the inventory_reservations table
        $I->dontSeeInDatabase('inventory_reservation');

        // Verify stock has been deducted on the order placement
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(9, $newQty, 'The quantity should have been decremented on creation of the order');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function repeatSavesOfOrderDoNotDecrementQuantity(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_repeat_saves', 100);

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_repeat_saves', 1);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');

        // Save this order a few times
        for ($i=0; $i<5; $i++) {
            $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders", json_encode([
                "entity"=> [
                    "entity_id" => $orderId,
                    "customer_is_guest" => 1,
                    "extension_attributes" => [
                        "payment_additional_info" => "payment_additional_info"
                    ]
                ]
            ]));
        }

        // Verify the stock has only gone down one, for the creation of the order and not the subsequent saves
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(99, $newQty, 'The quantity should have gone down by 1');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function preventStockDeductionOnOrderShipment(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_stock_deducts_on_shipment', 100);

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_stock_deducts_on_shipment', 3);
        $orderId = $I->completeGuestCheckout($cartId);

        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(97, $newQty);

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/ship");

        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(97, $newQty, 'The quantity should have been decremented on creation of the order and not changed since that point');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function stockIsReturnedWhenOrderIsCancelled(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_stock_returns_qty_on_cancel', 100);

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_stock_returns_qty_on_cancel', 5);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        //$I->haveRESTXdebugCookie(); # uncomment to add xdebug cookie to request
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders/{$orderId}/cancel");

        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(100, $newQty, 'The quantity should have been returned when cancelling');
    }

    /**
     * @link https://github.com/AmpersandHQ/magento2-disable-stock-reservation/pull/92
     *
     * @depends stockIsReturnedWhenOrderIsCancelled
     * @param Step\Acceptance\Magento $I
     */
    public function productGoesBackInStockWhenOrderIsRefunded(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_stock_returns_in_stock_on_refund',1);
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product has not started with qty=1'
        );
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product has not started with is_in_stock=1'
        );

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_stock_returns_in_stock_on_refund', 1);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product did not go qty=0 after an order'
        );
        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product did not go is_in_stock=0 after an order'
        );

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/invoice", json_encode([
            "capture" => true,
            "notify" => false
        ]));
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/ship");

        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product did not stay qty=0 after invoicing and shipping'
        );
        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product did not stay is_in_stock=0 after invoicing and shipping'
        );

        $orderItemId = $I->grabFromDatabase('sales_order_item', 'item_id', ['order_id' => $orderId]);

        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/refund", json_encode([
            "items" => [
                [
                    "order_item_id" => $orderItemId,
                    "qty" => 1
                ]
            ],
            "notify" => false,
            "arguments" => [
                "shipping_amount" =>  0,
                "adjustment_positive" => 0,
                "adjustment_negative" =>  0,
                "extension_attributes" => [
                    "return_to_stock_items" => [
                        $orderItemId
                    ]
                ]
            ]
        ]));

        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product did not go to is_in_stock=1 after a refund'
        );
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product did not go to qty=1 after a refund'
        );
    }

    /**
     * @link https://github.com/AmpersandHQ/magento2-disable-stock-reservation/pull/81
     *
     * @depends stockIsReturnedWhenOrderIsCancelled
     * @param Step\Acceptance\Magento $I
     */
    public function productGoesBackInStockWhenOrderIsCancelled(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_stock_returns_in_stock_on_cancel',1);
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product has not started with qty=1'
        );
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product has not started with is_in_stock=1'
        );

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_stock_returns_in_stock_on_cancel', 1);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product did not go qty=0 after an order'
        );
        $I->assertEquals(
            0,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product did not go is_in_stock=0 after an order'
        );

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        //$I->haveRESTXdebugCookie(); # uncomment to add xdebug cookie to request
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders/{$orderId}/cancel");

        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]),
            'Product did not go qty=1 after cancel'
        );
        $I->assertEquals(
            1,
            $I->grabFromDatabase('cataloginventory_stock_item', 'is_in_stock', ['product_id' => $productId]),
            'Product did not go is_in_stock=1 after cancel'
        );
    }

    /**
     * @link https://github.com/AmpersandHQ/magento2-disable-stock-reservation/issues/13
     *
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param \Step\Acceptance\Magento $I
     */
    public function stockDeductionPreventsSubsequentAddToBasket(Step\Acceptance\Magento $I)
    {
        // Create product with 50 stock
        $productId = $I->createSimpleProduct('amp_verify_stock_deduction_prevents_add_to_basket', 50);

        // Verify the state of the database at this point
        $qtyInDatabase = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(50, $qtyInDatabase, 'The product should be created with qty 50');

        $invQtyInDatabase = $I->grabFromDatabase('inventory_source_item', 'quantity', ['sku' => 'amp_verify_stock_deduction_prevents_add_to_basket']);
        $I->assertEquals(50, $invQtyInDatabase, 'The product should be created with qty 50');

        // Purchase 30 of unit, leaving 20
        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 30);
        $I->completeGuestCheckout($cartId);

        // Verify the state of the database at this point
        $qtyInDatabase = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals(20, $qtyInDatabase, 'The product should have a remaining qty of 20');

        $invQtyInDatabase = $I->grabFromDatabase('inventory_source_item', 'quantity', ['sku' => 'amp_verify_stock_deduction_prevents_add_to_basket']);
        $I->assertEquals(20, $invQtyInDatabase, 'The product should have a remaining qty of 20');

        // Add 30 of unit, 10 over the limit, this should error with "Requested qty is not available"
        $newCartId = $I->getGuestQuote();
        $I->expectThrowable(Exception\RequestedQtyNotAvailable::class, function () use ($newCartId, $I) {
            $I->addSimpleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 30);
        });

        // Add 20 of unit, this should work
        $I->addSimpleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 20);
    }
}
