<?php

class CheckoutCest
{
    /**
     * magerun2 integration:create disablestockres example@example.com https://example.com --access-token="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
     */
    const ACCESS_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    const SKU = 'ampersand-magento2-disable-stock-reservation-sku';

    private $productEntityId = null;
    private $productQty = null;

    /**
     * @param AcceptanceTester $I
     */
    public function dependenciesAreConfigured(AcceptanceTester $I)
    {
        $I->seeNumRecords(1, 'inventory_source');
        $I->seeInDatabase('core_config_data', ['path' => 'checkout/options/guest_checkout', 'value' => '1']);
        $I->seeInDatabase('core_config_data', ['path' => 'payment/checkmo/active', 'value' => '1']);
        $I->seeInDatabase('oauth_token', ['token' => self::ACCESS_TOKEN]);
        $I->deleteFromDatabase('inventory_reservation');

        $I->amGoingTo('Create our test product if it does not exist');
        $productEntityTypeId = $I->grabFromDatabase(
            'eav_entity_type',
            'entity_type_id',
            [
                'entity_type_code' => 'catalog_product'
            ]
        );
        $attributeSetId = $I->grabFromDatabase(
            'eav_attribute_set',
            'attribute_set_id',
            [
                'entity_type_id' => $productEntityTypeId,
                'attribute_set_name' => 'Default'
            ]
        );

        // Configure a small retry when doing the first API requests in case caches are slow to warm, curl could timeout
        $I->retry(4, 100);
        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->retrySendPOSTAndVerifyResponseCodeIs200('V1/products', json_encode([
            'product' => [
                'sku' => self::SKU,
                'name' => 'ampersand test product',
                'attribute_set_id' => $attributeSetId,
                'price' => 10,
                'status' => 1,
                'visibility' => 4,
                'type_id' => 'simple',
                'extension_attributes' => [
                    'stock_item' => [
                        'qty' => 100,
                        'is_in_stock' => true
                    ]
                ],
                'custom_attributes' => [
                    [
                        'attribute_code' => 'tax_class_id',
                        'value' => 2
                    ]
                ]
            ]
        ]));

        $productData = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('id', $productData);

        $this->productEntityId = $productData['id'];
    }

    /**
     * @depends dependenciesAreConfigured
     * @param AcceptanceTester $I
     */
    public function noInventoryIsReservedAndStockHasBeenDeducted(AcceptanceTester $I)
    {
        $this->createGuestCheckMoOrder($I);

        //Prevent all writes to the inventory_reservations table
        $I->dontSeeInDatabase('inventory_reservation');

        // Verify stock has been deducted on the order placement
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty-1, $newQty, 'The quantity should have been decremented on creation of the order');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param AcceptanceTester $I
     */
    public function repeatSavesOfOrderDoNotDecrementQuantityUn(AcceptanceTester $I)
    {
        $orderId = $this->createGuestCheckMoOrder($I);

        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
//        $I->haveRESTXdebugCookie(); # uncomment to add xdebug cookie to request

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
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty-1, $newQty, 'The quantity should have gone down by 1');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param AcceptanceTester $I
     */
    public function preventStockDeductionOnOrderShipment(AcceptanceTester $I)
    {
        $orderId = $this->createGuestCheckMoOrder($I);

        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/order/{$orderId}/ship");

        // Verify stock is -1 from the start of the tests. We already see that it was deducted in the above test
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty-1, $newQty, 'The quantity should have been decremented on creation of the order and not changed since that point');
    }

    /**
     * @depends noInventoryIsReservedAndStockHasBeenDeducted
     * @param AcceptanceTester $I
     */
    public function stockIsReturnedWhenOrderIsCancelled(AcceptanceTester $I)
    {
        $orderId = $this->createGuestCheckMoOrder($I);

        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        //$I->haveRESTXdebugCookie(); # uncomment to add xdebug cookie to request
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/orders/{$orderId}/cancel");

        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty, $newQty, 'The quantity should have been returned when cancelling');
    }

    /**
     * @param $I
     * @return string
     */
    private function createGuestCheckMoOrder($I)
    {
        /** @var AcceptanceTester $I*/
        $qty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertGreaterOrEquals(1, $qty, 'must have at least 1 qty in stock');
        $this->productQty = $qty;

        $I->haveHttpHeader('Content-Type', 'application/json');

        // Create a guest quote
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts");
        $cartId = str_replace('"', '', $I->grabResponse());

        // Add to basket
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts/$cartId/items", json_encode([
            'cartItem' => [
                'quoteId' => $cartId,
                'sku' => self::SKU,
                'qty' => 1,
            ],
        ]));

        //Estimate shipping method
        $email = "example" . time() . "@example.com";
        $address = [
            "email" => $email,
            "country_id" => 'GB',
            "street" => [
                "Street 0",
                "Street 1",
                "Street 2"
            ],
            "postcode" => "M1 1AA",
            "city" => "Manchester",
            "firstname" => "given name",
            "lastname" => "second name",
            "telephone" => "07700000000",
        ];

        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts/$cartId/estimate-shipping-methods", json_encode([
            "address" => array_merge($address, ["same_as_billing" => 1])
        ]));
        $I->assertStringContainsString('flatrate', $I->grabResponse(), 'Flat rate shipping method is not present');

        // Use flat rate shipping method
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts/$cartId/shipping-information", json_encode([
            "addressInformation" => [
                "shipping_address" => $address,
                "billing_address" => $address,
                "shipping_carrier_code" => "flatrate",
                "shipping_method_code" => "flatrate"
            ]
        ]));
        $I->assertStringContainsString('checkmo', $I->grabResponse(), 'Check Mo needs to be enabled');

        // Check out with checkmo
        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts/$cartId/payment-information", json_encode([
            'paymentMethod' => [
                'method' => 'checkmo',
            ],
            'billing_address' => $address,
            'email' => $email
        ]));
        $orderId = str_replace('"', '', $I->grabResponse());
        $I->comment('Have order with id ' . $orderId);
        return $orderId;
    }
}
