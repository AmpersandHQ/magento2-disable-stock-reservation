<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\InventoryInStorePickupSalesAdminUi;

use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;

/**
 * @magentoAppArea adminhtml
 */
class NotifyPickupControllerTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /** @var ProductFixture */
    private $productFixture;

    /** @var CustomerFixture */
    private $customerFixture;

    /**
     * Because stock reservation on order placement is disabled, we are trying to notify on products which may either
     * have some stock, or may be all the way at 0 stock.
     *
     * The admin user clicks this button to notify the customer it's ready for pickup, but without the changes in
     * IsFulfillablePlugin we cant actually let them know when the stock it at 0 because it fails the isItemFulfillable
     * check
     *
     * @link https://github.com/magento/inventory/blob/c4e3a4ef/InventoryInStorePickupSalesAdminUi/Controller/Adminhtml/Order/NotifyPickup.php#L73-L95
     * @link https://github.com/magento/inventory/blob/c4e3a4ef/InventoryInStorePickupSales/Model/Order/IsFulfillable.php#L68-L84
     * @link https://github.com/AmpersandHQ/magento2-disable-stock-reservation/pull/109
     * @link https://github.com/AmpersandHQ/magento2-disable-stock-reservation/issues/69
     *
     * @magentoAppIsolation enabled
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testNotifyProducts()
    {
        if (!class_exists(\Magento\InventoryInStorePickupSales\Model\Order\IsFulfillable::class)) {
            $this->markTestSkipped('Test not required on older magento versions');
            return;
        }

        /**
         * Create a customer and login
         */
        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->asDefaultBilling(),
            AddressBuilder::anAddress()->asDefaultShipping()
        )->build());
        $this->customerFixture->login();

        /**
         * Create a product with 5 qty
         */
        $this->productFixture = new ProductFixture(
            ProductBuilder::aSimpleProduct()
                ->withPrice(10)
                ->withStockQty(5)
                ->withIsInStock(true)
                ->build()
        );

        /**
         * Order 5 qty
         */
        $checkout = CustomerCheckout::fromCart(
            CartBuilder::forCurrentSession()
                ->withSimpleProduct(
                    $this->productFixture->getSku(),
                    5
                )
                ->build()
        );
        $order = $checkout->placeOrder();
        $extensionAttributes = $order->getExtensionAttributes();
        $extensionAttributes->setPickupLocationCode('abc123');
        $order->setExtensionAttributes($extensionAttributes);
        $order->save();

        $this->assertGreaterThan(0, strlen($order->getIncrementId()), 'the order does not have a valid increment_id');
        $this->assertIsNumeric($order->getId(), 'the order does not have an entity_id');

        /**
         * Load the product fresh, and confirm it has had stock decremented (because reservations are disabled)
         */
        $product = Bootstrap::getObjectManager()
            ->get(\Magento\Catalog\Model\ProductFactory::class)
            ->create()
            ->loadByAttribute('sku', $this->productFixture->getSku());
        $this->assertEquals($product->getId(), $this->productFixture->getId(), 'The product failed to load');
        $this->assertEquals(0, $product->getQty(), 'The product should have qty=0 after the order');
        $this->assertEquals(0, $product->getIsInStock(), 'The product should have is_in_stock=0 after the order');

        /**
         * Dispatch the notfiyPickup controller
         *
         * It should respond that the customer has been notified
         *
         * The plugin will force every case to be a "true" result for pickup being available, the only thing that
         * disqualifies a product from being able to be picked up in the original implementation is it being OOS
         *
         * @link https://github.com/magento/inventory/blob/develop/InventoryInStorePickupSalesAdminUi/Controller/Adminhtml/Order/NotifyPickup.php
         */
        $this->getRequest()->setParam(
            'order_id',
            $order->getId()
        );
        $this->dispatch('backend/sales/order/notifyPickup');
        $this->assertEquals(1, count($this->getSessionMessages()), 'We should only have 1 session message');
        $this->assertSessionMessages(
            $this->equalTo(
                ['The customer has been notified and shipment created.'],
                \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS
            )
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (isset($this->productFixture)) {
            $this->productFixture->rollback();
        }
        if (isset($this->customerFixture)) {
            $this->customerFixture->rollback();;
        }
    }
}
