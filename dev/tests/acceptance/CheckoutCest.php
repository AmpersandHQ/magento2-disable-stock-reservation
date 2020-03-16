<?php

class CheckoutCest
{
    /**
     * magerun2 integration:create disablestockres example@example.com https://example.com --access-token="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
     */
    const ACCESS_TOKEN='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    const SKU='24-MB02';

    private $productEntityId = null;
    private $productQty = null;
    private $orderId = null;

    /**
     * @param AcceptanceTester $I
     */
    public function databaseIsConfigured(AcceptanceTester $I)
    {
        $I->seeNumRecords(1, 'inventory_source');
        $I->seeInDatabase('core_config_data', ['path' => 'checkout/options/guest_checkout', 'value' => '1']);
        $I->seeInDatabase('core_config_data', ['path' => 'payment/checkmo/active', 'value' => '1']);
        $I->seeInDatabase('oauth_token', ['token'=> self::ACCESS_TOKEN]);
        $I->deleteFromDatabase('sales_order');
        $I->deleteFromDatabase('quote');
        $I->deleteFromDatabase('inventory_reservation');
        $I->seeInDatabase('catalog_product_entity', ['sku' => self::SKU]);

        // Get product quantity
        $productEntityId = $I->grabFromDatabase('catalog_product_entity', 'entity_id', ['sku' => self::SKU]);
        $qty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $productEntityId]);
        $I->assertGreaterOrEquals(1, $qty, '24-MB02 must have at least 1 qty in stock');
        $this->productQty = $qty;
        $this->productEntityId = $productEntityId;
    }

    /**
     * @depends databaseIsConfigured
     *
     * @param AcceptanceTester $I
     */
    public function addToBasketThenCheckout(AcceptanceTester $I)
    {
        // Configure a forgiving retry amount
        $I->retry(10, 100);

        $I->amOnPage('fusion-backpack.html');

        // Add to basket
        $I->retryClick('button[title="Add to Cart"]');
        $I->waitAjaxLoad();
        $I->retrySeeInDatabase('quote');

        // Load checkout
        $I->amOnPage('checkout');
        $I->dontSeeInCurrentUrl('cart');                    // Verify that guest checkout is enabled, no 302 away
        $I->waitForElementNotVisible('#checkout-loader', 30);   // Wait for the checkout to actually render

        // Fill in checkout
        // Pick United Kingdom from country dropdown
        $I->click('select[name="country_id"]');
        $I->click('select[name="country_id"] > option[value=GB]');

        $I->fillField('#customer-email', 'example' . time() . '@example.com');
        $I->fillField('input[name="firstname"]', 'given name');
        $I->fillField('input[name="lastname"]', 'second name');
        $I->fillField('input[name="street[0]"]', 'street 0');
        $I->fillField('input[name="street[1]"]', 'street 1');
        $I->fillField('input[name="street[2]"]', 'street 2');
        $I->fillField('input[name="city"]', 'city');
        $I->fillField('input[name="postcode"]', 'M1 1AA');
        $I->fillField('input[name="telephone"]', '07700000000');

        // Pick shipping method
        $I->waitForElementNotVisible('#opc-shipping_method > div.loading-mask'); // Ensure shipping methods loaded
        $I->retryDontSee('Best Way');
        $I->see('Flat Rate');
        $I->retryClick('input[value="flatrate_flatrate"]');
        $I->retryClick('button[data-role="opc-continue"]');
        $I->waitForElementNotVisible('#checkout-loader', 30);

        // Place order and verify we hit the success page
        $I->retrySee('Place Order');
        $I->retryClick('button.primary.checkout[type="submit"]');
        $I->waitForElementNotVisible('#checkout-loader', 30);   // Wait for the checkout to actually render
        $I->waitPageLoad();
        $I->seeInCurrentUrl('success');

        $this->orderId = $I->grabFromDatabase('sales_order', 'entity_id');
        $I->comment('Have order with id' . $this->orderId);
    }

    /**
     * @depends addToBasketThenCheckout
     * @param AcceptanceTester $I
     */
    public function noInventoryIsReservedAndStockHasBeenDeducted(AcceptanceTester $I)
    {
        //Prevent all writes to the inventory_reservations table
        $I->dontSeeInDatabase('inventory_reservation');

        // Verify stock has been deducted on the order placement
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty-1, $newQty, 'The quantity should have been decremented on creation of the order');
    }

    /**
     * @depends CheckoutCest:noInventoryIsReservedAndStockHasBeenDeducted
     * @param AcceptanceTester $I
     */
    public function preventStockDeductionOnOrderShipment(AcceptanceTester $I)
    {
        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST("order/{$this->orderId}/ship");
        $I->seeResponseCodeIs(200);

        // Verify stock is -1 from the start of the tests. We already see that it was deducted in the above test
        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty-1, $newQty, 'The quantity should have been decremented on creation of the order and not changed since that point');
    }

    /**
     * @depends CheckoutCest:preventStockDeductionOnOrderShipment
     * @param AcceptanceTester $I
     */
    public function stockIsReturnedWhenOrderIsCancelled(AcceptanceTester $I)
    {
        $I->amBearerAuthenticated(self::ACCESS_TOKEN);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST("orders/{$this->orderId}/cancel");
        $I->seeResponseCodeIs(200);

        $newQty = $I->grabFromDatabase('cataloginventory_stock_item', 'qty', ['product_id' => $this->productEntityId]);
        $I->assertEquals($this->productQty, $newQty, 'The quantity should have been returned when cancelling');
    }
}
