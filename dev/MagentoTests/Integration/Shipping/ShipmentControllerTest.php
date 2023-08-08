<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\Refund;

require_once __DIR__ . '/../../Helper/IntegrationHelper.php';
use Ampersand\DisableStockReservation\Test\Helper\IntegrationHelper as TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Sales\InvoiceBuilder;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;

/**
 * @magentoAppArea adminhtml
 */
class ShipmentControllerTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /** @var ProductFixture */
    private $productFixture;

    /** @var CustomerFixture */
    private $customerFixture;

    public function testOrderShipmentsAreValidWhenZeroStock()
    {
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

        $stockItem = $this->getStockItem($this->productFixture->getSku());
        $this->assertTrue($stockItem->getIsInStock(), 'Product should be created in stock');
        $this->assertEquals(5, $stockItem->getQty(), 'Product should be created qty=5');
        $this->assertEquals(
            5,
            $this->getSource($this->productFixture->getSku())->getQuantity(),
            'The product source should have qty=0 when it is created'
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
        $order = $checkout
            ->withPaymentMethodCode('checkmo')
            ->placeOrder();
        $this->assertGreaterThan(0, strlen($order->getIncrementId()), 'the order does not have a valid increment_id');
        $this->assertIsNumeric($order->getId(), 'the order does not have an entity_id');

        $this->assertEquals(
            0,
            $this->getSource($this->productFixture->getSku())->getQuantity(),
            'The product source should have qty=0 after the order'
        );
        $stockItem = $this->getStockItem($this->productFixture->getSku());
        $this->assertEquals(0, $stockItem->getQty(), 'Product should go qty=0 after purchase');
        $this->assertFalse($stockItem->getIsInStock(), 'Product should go is_in_stock=0 after purchase');

        /**
         * Invoice
         */
        $this->assertTrue($order->canInvoice(), 'The order cannot have an invoice created');
        $invoice = InvoiceBuilder::forOrder($order)->build();

        /**
         * Ship via controller
         */
        $this->assertTrue($order->canShip(), 'The order cannot be shipped');

        TestHelper::clearCaches();
        
        /** @var $item \Magento\Sales\Model\Order\Item */
        $item = $order->getAllItems()[0];
        $this->getRequest()->setParam(
            'order_id',
            $order->getId()
        );
        $this->getRequest()->setPostValue('sourceCode', 'default');
        $this->getRequest()->setPostValue(
            'shipment',
            [
                'comment_text' => '',
                'items' => [
                    $item->getId() => 5
                ],
            ]
        );
        $this->getRequest()->setMethod('POST');
        $this->dispatch('backend/admin/order_shipment/save');
        $this->assertEquals(1, count($this->getSessionMessages()), 'We should only have 1 session message');
        $this->assertSessionMessages(
            $this->equalTo(
                ['The shipment has been created.'],
                \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS
            )
        );

        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        foreach ($order->getShipmentsCollection() as $shipment) {
            break;
        }
        $this->assertGreaterThan(0, strlen($shipment->getId()), 'the shipment does not have a valid id');

        /**
         * Verify the shipment was created with qty 5 shipped and that the product is still OOS and unsaleable
         */
        foreach ($shipment->getItems() as $shipmentItem) {
            break;
        }
        $this->assertGreaterThan(0, strlen($shipmentItem->getId()), 'the shipment item does not have a valid id');
        
        $this->assertEquals(5, $shipmentItem->getQty(), 'The shipment was not for the full qty');
        $this->assertEquals($this->productFixture->getSku(), $shipmentItem->getSku(), 'The shipment was not for the sku');
        
        $stockItem = $this->getStockItem($this->productFixture->getSku());
        $this->assertEquals(
            0,
            $stockItem->getIsInStock(),
            'is_in_stock=0 should still be set'
        );
        $this->assertEquals(
            0,
            $stockItem->getQty(),
            'qty should still be zero'
        );
        $this->assertEquals(
            0,
            $this->getSource($this->productFixture->getSku())->getQuantity()
        );
    }

    /**
     * @param $sku
     * @return mixed|null
     */
    private function getSource($sku)
    {
        /** @var \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface $getStockItems */
        $getSourceItemsBySku = Bootstrap::getObjectManager()->get(\Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class);
        $sources = $getSourceItemsBySku->execute($this->productFixture->getSku());
        $this->assertIsArray($sources);
        $this->assertCount(1, $sources);
        $source = array_pop($sources);
        return $source;
    }

    /**
     * @param $sku
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface\
     */
    private function getStockItem($sku)
    {
        TestHelper::clearCaches();
        $registry = Bootstrap::getObjectManager()->create(\Magento\CatalogInventory\Model\StockRegistry::class);
        /** @var \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem */
        $stockItem =  $registry->getStockItemBySku($sku);
        $this->assertGreaterThan(0, strlen($stockItem->getItemId()));

        TestHelper::clearCaches();
        return $stockItem;
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
