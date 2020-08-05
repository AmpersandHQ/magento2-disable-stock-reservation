<?php

namespace Step\Acceptance;

class Magento extends \AcceptanceTester
{
    /**
     * magerun2 integration:create disablestockres example@example.com https://example.com --access-token="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
     */
    const ACCESS_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /**
     * @param $sku
     * @param $qty
     * @return int
     */
    public function createSimpleProduct($sku, $qty)
    {
        $I = $this;

        $I->amGoingTo('create our test product if it does not exist');
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
                'sku' => $sku,
                'name' => $sku,
                'attribute_set_id' => $attributeSetId,
                'price' => 10,
                'status' => 1,
                'visibility' => 4,
                'type_id' => 'simple',
                'extension_attributes' => [
                    'stock_item' => [
                        'qty' => $qty,
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

        $productData = \json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('id', $productData);
        $productId = $productData['id'];

        $qtyInDatabase = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productId]);
        $I->assertEquals($qty, $qtyInDatabase, 'The product should have been created with a quantity of  ' . $qty);

        return $productId;
    }

    /**
     * @param $sku
     * @param $qty
     * @return int
     */
    public function createBundleProduct($sku, array $simples = [])
    {
        $I = $this;

        $I->amGoingTo('create our test bundle product if it does not exist');
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
                'sku' => $sku,
                'name' => $sku,
                'attribute_set_id' => $attributeSetId,
                'price' => 0,
                'weight' => 0,
                'status' => 1,
                'visibility' => 4,
                'type_id' => 'bundle',
                'extension_attributes' => [
                    'stock_item' => [
                        'qty' => 0,
                        'is_in_stock' => true
                    ],
                    'bundle_product_options' => array_map(function($simple) use ($sku){
                        return [
                            "option_id" => 8,
                            "title" => "test",
                            "required" => true,
                            "type" => "select",
                            "position" => 1,
                            "sku" => $sku,
                            "product_links" => [
                                [
                                    "id" => "4",
                                    "sku" => $simple['sku'],
                                    "option_id" => 8,
                                    "qty" => 1,
                                    "position" => 1,
                                    "is_default" => false,
                                    "price" => 100,
                                    "can_change_quantity" => 0
                                ]
                            ]
                        ];
                    }, $simples),
                ],
                'custom_attributes' => [
                    [
                        'attribute_code' => 'tax_class_id',
                        'value' => 2
                    ],
                    [
                        'attribute_code' => 'price_view',
                        'value' => 0
                    ],
                    [
                        'attribute_code' => 'url_key',
                        'value' => $sku
                    ],
                    [
                        'attribute_code' => 'price_view',
                        'value' => 0
                    ],
                    [
                        'attribute_code' => 'required_options',
                        'value' => 1
                    ],
                    [
                        'attribute_code' => 'has_options',
                        'value' => 1
                    ],
                    [
                        'attribute_code' => 'sku_type',
                        'value' => 0
                    ],
                    [
                        'attribute_code' => 'price_type',
                        'value' => 0
                    ]
                ]
            ]
        ]));

        $productData = \json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('id', $productData);
        return $productData['id'];
    }

    /**
     * @return string|string[]
     */
    public function getGuestQuote()
    {
        $I = $this;

        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->sendPOSTAndVerifyResponseCodeIs200("V1/guest-carts");
        return str_replace('"', '', $I->grabResponse());
    }

    /**
     * @param $cartId
     * @param $sku
     * @param $qty
     * @throws \Exception\RequestedQtyNotAvailable
     */
    public function addSimpleProductToQuote($cartId, $sku, $qty)
    {
        $I = $this;

        $I->sendPOST("V1/guest-carts/$cartId/items", json_encode([
            'cartItem' => [
                'quoteId' => $cartId,
                'sku' => $sku,
                'qty' => $qty,
            ],
        ]));

        /**
         * This allows us to assume this function works with a 200 response most of the time, but still catch and handle
         * specific errors
         */
        if ($I->tryToAssertStringContainsString('The requested qty is not available', $I->grabResponse())) {
            throw new \Exception\RequestedQtyNotAvailable('The requested qty is not available');
        }

        $I->seeResponseCodeIs(200);
    }

    /**
     * @param $cartId
     * @param $sku
     * @param $qty
     * @throws \Exception\RequestedQtyNotAvailable
     */
    public function addBundleProductToQuote($cartId, $sku, $qty)
    {
        $I = $this;

        $I->sendGET("V1/bundle-products/{$sku}/options/all");

        $availableOptions = \GuzzleHttp\json_decode($I->grabResponse(), true);

        $I->sendPOST("V1/guest-carts/$cartId/items", json_encode([
            'cartItem' => [
                'quoteId' => $cartId,
                'sku' => $sku,
                'qty' => $qty,
                'product_type' => 'bundle',
                'product_option' => [
                    "extension_attributes" => [
                        "bundle_options" => [
                            [
                                "option_id" => $availableOptions[0]['option_id'],
                                "option_qty" => 1,
                                "option_selections" => [
                                    $availableOptions[0]['product_links'][0]['id']
                                ],
                            ],
                            [
                                "option_id" => $availableOptions[1]['option_id'],
                                "option_qty" => 1,
                                "option_selections" => [
                                    $availableOptions[1]['product_links'][0]['id']
                                ],
                            ]
                        ]
                    ]
                ]
            ],
        ]));

        /**
         * This allows us to assume this function works with a 200 response most of the time, but still catch and handle
         * specific errors
         */
        if ($I->tryToAssertStringContainsString('The requested qty is not available', $I->grabResponse())) {
            throw new \Exception\RequestedQtyNotAvailable('The requested qty is not available');
        }

        $I->seeResponseCodeIs(200);
    }

    /**
     * @param $cartId
     * @return string
     */
    public function completeGuestCheckout($cartId)
    {
        $I = $this;

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
        return str_replace('"', '', $I->grabResponse());
    }
}
