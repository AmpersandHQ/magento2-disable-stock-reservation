<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\Cancel;

require_once __DIR__ . '/../../Helper/IntegrationHelper.php';
use Ampersand\DisableStockReservation\Test\Helper\IntegrationHelper as TestHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use PHPUnit\Framework\TestCase;

class CancelOrderBackInStockConfigTest extends TestCase
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ProductFixture */
    private $productFixture;

    /** @var CustomerFixture */
    private $customerFixture;

    public function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productRepository->cleanCache();
    }

    /**
     * This should be the same as testCancelOrderBackInStockEnabled
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testCancelOrderBackInStockDefault()
    {
        $sku = uniqid('cancel_order_back_in_stock_default');

        /**
         * Create a product with 5 qty
         */
        $this->productFixture = new ProductFixture(
            ProductBuilder::aSimpleProduct()
                ->withSku($sku)
                ->withPrice(10)
                ->withStockQty(5)
                ->withIsInStock(true)
                ->build()
        );

        $this->assertEquals(5, $this->getStockItem($sku)->getQty(), 'The stock did start at 5');

        /**
         * Create a customer and login
         */
        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->asDefaultBilling(),
            AddressBuilder::anAddress()->asDefaultShipping()
        )->build());
        $this->customerFixture->login();

        /**
         * Order 1 qty
         */
        $checkout = CustomerCheckout::fromCart(
            CartBuilder::forCurrentSession()
                ->withSimpleProduct(
                    $this->productFixture->getSku(),
                    1
                )
                ->build()
        );

        $order = $checkout
            ->withPaymentMethodCode('checkmo')
            ->placeOrder();
        $this->assertGreaterThan(0, strlen($order->getIncrementId()), 'the order does not have a valid increment_id');
        $this->assertIsNumeric($order->getId(), 'the order does not have an entity_id');

        $this->assertEquals(4, $this->getStockItem($sku)->getQty(), 'The stock did not go down to 4');

        /**
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');

        $this->assertEquals(5, $this->getStockItem($sku)->getQty(), 'The stock did not go back to 5');
    }

    /**
     * @magentoConfigFixture current_store cataloginventory/options/can_back_in_stock 1
     */
    public function testCancelOrderBackInStockEnabled()
    {
        $sku = uniqid('cancel_order_back_in_stock_enabled');

        /**
         * Create a product with 5 qty
         */
        $this->productFixture = new ProductFixture(
            ProductBuilder::aSimpleProduct()
                ->withSku($sku)
                ->withPrice(10)
                ->withStockQty(5)
                ->withIsInStock(true)
                ->build()
        );

        $this->assertEquals(5, $this->getStockItem($sku)->getQty(), 'The stock did start at 5');

        /**
         * Create a customer and login
         */
        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->asDefaultBilling(),
            AddressBuilder::anAddress()->asDefaultShipping()
        )->build());
        $this->customerFixture->login();

        /**
         * Order 1 qty
         */
        $checkout = CustomerCheckout::fromCart(
            CartBuilder::forCurrentSession()
                ->withSimpleProduct(
                    $this->productFixture->getSku(),
                    1
                )
                ->build()
        );

        $order = $checkout
            ->withPaymentMethodCode('checkmo')
            ->placeOrder();
        $this->assertGreaterThan(0, strlen($order->getIncrementId()), 'the order does not have a valid increment_id');
        $this->assertIsNumeric($order->getId(), 'the order does not have an entity_id');

        $this->assertEquals(4, $this->getStockItem($sku)->getQty(), 'The stock did not go down to 4');

        /**
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');

        $this->assertEquals(5, $this->getStockItem($sku)->getQty(), 'The stock did not go back to 5');
    }

    /**
     * @magentoConfigFixture current_store cataloginventory/options/can_back_in_stock 0
     */
    public function testCancelOrderBackInStockDisabled()
    {
        $sku = uniqid('cancel_order_back_in_stock_disabled');

        /**
         * Create a product with 5 qty
         */
        $this->productFixture = new ProductFixture(
            ProductBuilder::aSimpleProduct()
                ->withSku($sku)
                ->withPrice(10)
                ->withStockQty(5)
                ->withIsInStock(true)
                ->build()
        );

        $this->assertEquals(5, $this->getStockItem($sku)->getQty(), 'The stock did start at 5');

        /**
         * Create a customer and login
         */
        $this->customerFixture = new CustomerFixture(CustomerBuilder::aCustomer()->withAddresses(
            AddressBuilder::anAddress()->asDefaultBilling(),
            AddressBuilder::anAddress()->asDefaultShipping()
        )->build());
        $this->customerFixture->login();

        /**
         * Order 1 qty
         */
        $checkout = CustomerCheckout::fromCart(
            CartBuilder::forCurrentSession()
                ->withSimpleProduct(
                    $this->productFixture->getSku(),
                    1
                )
                ->build()
        );

        $order = $checkout
            ->withPaymentMethodCode('checkmo')
            ->placeOrder();
        $this->assertGreaterThan(0, strlen($order->getIncrementId()), 'the order does not have a valid increment_id');
        $this->assertIsNumeric($order->getId(), 'the order does not have an entity_id');

        $this->assertEquals(4, $this->getStockItem($sku)->getQty(), 'The stock did not go down to 4');

        /**
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');

        $this->assertEquals(4, $this->getStockItem($sku)->getQty(), 'The stock did not stay at 4');
    }

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
}
