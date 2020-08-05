<?php

class CheckoutCest
{
    /**
     * @param Step\Acceptance\Magento $I
     */
    public function dependenciesAreConfigured(Step\Acceptance\Magento $I)
    {
        $I->seeNumRecords(1, 'inventory_source');
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
        $I->assertEquals(97, $newQty, 'qty should have been decremented on creation of the order and not changed later');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function preventStockDeductionOnOrderShipmentForBundles(Step\Acceptance\Magento $I)
    {
        $productIdA = $I->createSimpleProduct($firstSku = 'amp_stock_deducts_on_shipment_bundle_a', 100);
        $productIdB = $I->createSimpleProduct($secondSku = 'amp_stock_deducts_on_shipment_bundle_b', 100);

        // Create product with 50 stock
        $I->createBundleProduct('amp_stock_deducts_on_shipment_bundle', [
            ['sku' => $firstSku, 'id' => $productIdA],
            ['sku' => $secondSku, 'id' => $productIdB]
        ]);

        $cartId = $I->getGuestQuote();
        $I->addBundleProductToQuote($cartId, 'amp_stock_deducts_on_shipment_bundle', 3);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->assertEquals(97, $this->getStockQty($I, $productIdA));
        $I->assertEquals(97, $this->getStockQty($I, $productIdB));

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/ship");

        // The quantity should have been decremented on creation of the order and not changed since that point
        $I->assertEquals(97, $this->getStockQty($I, $productIdA));
        $I->assertEquals(97, $this->getStockQty($I, $productIdB));
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function stockIsReturnedWhenOrderIsCancelled(Step\Acceptance\Magento $I)
    {
        $productId = $I->createSimpleProduct('amp_stock_deducts_on_shipment', 100);

        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_stock_deducts_on_shipment', 5);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        //$I->haveRESTXdebugCookie(); # uncomment to add xdebug cookie to request
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders/{$orderId}/cancel");

        $I->assertEquals(100, $this->getStockQty($I, $productId), 'The qty should have been returned when cancelling');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param Step\Acceptance\Magento $I
     */
    public function stockIsReturnedWhenOrderIsCancelledForBundles(Step\Acceptance\Magento $I)
    {
        $productIdA = $I->createSimpleProduct($firstSku = 'amp_stock_deducts_on_shipment_bundle_a', 100);
        $productIdB = $I->createSimpleProduct($secondSku = 'amp_stock_deducts_on_shipment_bundle_b', 100);

        // Create product with 50 stock
        $I->createBundleProduct('amp_stock_deducts_on_shipment_bundle', [
            ['sku' => $firstSku, 'id' => $productIdA],
            ['sku' => $secondSku, 'id' => $productIdB]
        ]);

        $cartId = $I->getGuestQuote();
        $I->addBundleProductToQuote($cartId, 'amp_stock_deducts_on_shipment_bundle', 5);
        $orderId = $I->completeGuestCheckout($cartId);

        $I->amBearerAuthenticated(Step\Acceptance\Magento::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders/{$orderId}/cancel");

        // The quantity should have been returned when cancelling
        $I->assertEquals(100, $this->getStockQty($I, $productIdA));
        $I->assertEquals(100, $this->getStockQty($I, $productIdB));
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

        // Verify the state of the database at this point, The product should be created with qty 50
        $I->assertEquals(50, $this->getStockQty($I, $productId));
        $I->assertEquals(50, $this->getInventoryQty($I, 'amp_verify_stock_deduction_prevents_add_to_basket'));

        // Purchase 30 of unit, leaving 20
        $cartId = $I->getGuestQuote();
        $I->addSimpleProductToQuote($cartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 30);
        $I->completeGuestCheckout($cartId);

        // Verify the state of the database at this point, The product should have a remaining qty of 20
        $I->assertEquals(20, $this->getStockQty($I, $productId));
        $I->assertEquals(20, $this->getInventoryQty($I, 'amp_verify_stock_deduction_prevents_add_to_basket'));

        // Add 30 of unit, 10 over the limit, this should error with "Requested qty is not available"
        $newCartId = $I->getGuestQuote();
        $I->expectThrowable(Exception\RequestedQtyNotAvailable::class, function () use ($newCartId, $I) {
            $I->addSimpleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 30);
        });

        // Add 20 of unit, this should work
        $I->addSimpleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket', 20);
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param \Step\Acceptance\Magento $I
     */
    public function stockDeductionPreventsSubsequentAddToBasketForBundles(Step\Acceptance\Magento $I)
    {
        $firstProductId = $I->createSimpleProduct($firstSku = 'bundle_simple_a', 50);
        $secondProductId = $I->createSimpleProduct($secondSku = 'bundle_simple_b', 50);

        // Create product with 50 stock
        $I->createBundleProduct('amp_verify_stock_deduction_prevents_add_to_basket_bundle', [
            ['sku' => $firstSku, 'id' => $firstProductId],
            ['sku' => $secondSku, 'id' => $secondProductId]
        ]);

        // Verify the state of the database at this point
        $I->assertEquals(50, $this->getStockQty($I, $firstProductId), 'The product A should be created with qty 50');
        $I->assertEquals(50, $this->getInventoryQty($I, $firstSku), 'The product A should be created with qty 50');

        $I->assertEquals(50, $this->getStockQty($I, $secondProductId), 'The product B should be created with qty 50');
        $I->assertEquals(50, $this->getInventoryQty($I, $secondSku), 'The product B should be created with qty 50');

        // Purchase 30 of unit, leaving 20
        $cartId = $I->getGuestQuote();
        $I->addBundleProductToQuote($cartId, 'amp_verify_stock_deduction_prevents_add_to_basket_bundle', 30);
        $I->completeGuestCheckout($cartId);

        // Verify the state of the database at this point
        $I->assertEquals(20, $this->getStockQty($I, $firstProductId), 'The product A should be created with qty 20');
        $I->assertEquals(20, $this->getInventoryQty($I, $firstSku), 'The product A should be created with qty 20');

        $I->assertEquals(20, $this->getStockQty($I, $secondProductId), 'The product B should be created with qty 20');
        $I->assertEquals(20, $this->getInventoryQty($I, $secondSku), 'The product B should be created with qty 20');

        // Add 30 of unit, 10 over the limit, this should error with "Requested qty is not available"
        $newCartId = $I->getGuestQuote();
        $I->expectThrowable(Exception\RequestedQtyNotAvailable::class, function () use ($newCartId, $I) {
            $I->addBundleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket_bundle', 30);
        });

        // Add 20 of unit, this should work
        $I->addBundleProductToQuote($newCartId, 'amp_verify_stock_deduction_prevents_add_to_basket_bundle', 20);
    }

    private function getStockQty(Step\Acceptance\Magento $I, $productId)
    {
        return $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
    }

    private function getInventoryQty(Step\Acceptance\Magento $I, $sku)
    {
        return $I->grabFromDatabase('inventory_source_item', 'quantity', ['sku' => $sku]);
    }
}
