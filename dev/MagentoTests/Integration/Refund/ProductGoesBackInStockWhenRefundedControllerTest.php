<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\Refund;

require_once __DIR__ . '/../../Helper/IntegrationHelper.php';
use Ampersand\DisableStockReservation\Test\Helper\IntegrationHelper as TestHelper;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Sales\ShipmentBuilder;
use TddWizard\Fixtures\Sales\InvoiceBuilder;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;

/**
 * @magentoAppArea adminhtml
 */
class ProductGoesBackInStockWhenRefundedTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /** @var ProductFixture */
    private $productFixture;

    /** @var CustomerFixture */
    private $customerFixture;


    /**
     * @dataProvider  productBackInStockHandlingDataProvider
     */
    public function testProductBackInStockHandling($backToStock, $expectedStockData)
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
         * Invoice and ship
         */
        $this->assertTrue($order->canInvoice(), 'The order cannot have an invoice created');
        $invoice = InvoiceBuilder::forOrder($order)->build();
        $this->assertTrue($order->canShip(), 'The order cannot be shipped');
        $shipment = ShipmentBuilder::forOrder($order)->build();
        $this->assertGreaterThan(0, strlen($shipment->getIncrementId()), 'the shipment does not have a valid increment_id');
        $order->save();

        /**
         * Create a credit memo with return_to_stock (aka setBacKToStock)
         */
        $this->assertTrue($order->canCreditmemo(), 'The order cannot have a credit memo created');

        /** @var $item \Magento\Sales\Model\Order\Item */
        $item = $order->getAllItems()[0];
        $this->getRequest()->setParam(
            'order_id',
            $order->getId()
        );
        $payload = [
            'do_offline' => 1,
            'comment_text' => '',
            'shipping_amount' => 0,
            'adjustment_positive' => 0,
            'adjustment_negative' => 0
        ];
        if ($backToStock) {
            $payload['items'] = [
                    $item->getId() => [
                    'back_to_stock' => $backToStock,
                    'qty' => 5
                ]
            ];
        }

        $this->getRequest()->setPostValue(
            'creditmemo',
            $payload
        );
        $this->getRequest()->setMethod('POST');
        $this->dispatch('backend/sales/order_creditmemo/save');
        $this->assertEquals(1, count($this->getSessionMessages()), 'We should only have 1 session message');
        $this->assertSessionMessages(
            $this->equalTo(
                ['You created the credit memo.'],
                \Magento\Framework\Message\MessageInterface::TYPE_SUCCESS
            )
        );

        $stockItem = $this->getStockItem($this->productFixture->getSku());
        $this->assertEquals(
            $expectedStockData['is_in_stock'],
            $stockItem->getIsInStock(),
            'is_in_stock does not match expected'
        );
        $this->assertEquals(
            $expectedStockData['qty'],
            $stockItem->getQty(),
            'qty does not match expected'
        );

        $this->assertEquals(
            $expectedStockData['qty'],
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

    public function productBackInStockHandlingDataProvider(): array
    {
        return [
            'back_to_stock=true returns items' => [
                'back_to_stock' => '1',
                'expected_stock_data' => [
                    'is_in_stock' => true,
                    'qty' => 5
                ]
            ],
            'back_to_stock=false does not return items' => [
                'back_to_stock' => '0',
                'expected_stock_data' => [
                    'is_in_stock' => false,
                    'qty' => 0
                ]
            ],
        ];
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
