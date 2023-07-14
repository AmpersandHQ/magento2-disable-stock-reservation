<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\Cancel;

use Magento\Framework\Registry;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\InventorySales\Model\GetStockBySalesChannelCache;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Sales\ShipmentBuilder;
use TddWizard\Fixtures\Sales\InvoiceBuilder;
use TddWizard\Fixtures\Checkout\CustomerCheckout;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use PHPUnit\Framework\TestCase;

class CancelOrderWithDeletedProduct extends TestCase
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var ClearQueueProcessor */
    private $clearQueueProcessor;

    /** @var GetSourceItemsBySkuInterface */
    private $getSourceItemsBySku;

    /** @var ConsumerFactory */
    private $consumerFactory;
    
    /** @var ProductRepositoryInterface */
    private $productRepository;
    
    /** @var Registry */
    private $registry;
    
    /** @var ProductFixture */
    private $productFixture;

    /** @var CustomerFixture */
    private $customerFixture;
    
    public function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->clearQueueProcessor = $this->objectManager->get(ClearQueueProcessor::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productRepository->cleanCache();
        $this->getSourceItemsBySku = $this->objectManager->get(GetSourceItemsBySkuInterface::class);
        $this->consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $this->registry = $this->objectManager->get(Registry::class);
    }

    /**
     * @magentoConfigFixture default/cataloginventory/options/synchronize_with_catalog 0
     */
    public function testCancelOrderWithDeletedProductAndNotSyncWithCatalog()
    {
        $sku = uniqid('product_will_be_deleted');

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

        /**
         * Ensure consumers are cleared before next steps
         */
        $this->clearQueueProcessor->execute('inventory.source.items.cleanup');

        /**
         * Delete the product
         */
        $origVal = $this->registry->registry('isSecureArea');
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);
        $product = $this->productRepository->deleteById($sku);
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', $origVal);

        try {
            $this->productRepository->getById($sku);
            $this->fail('This product should not be loadable: ' . $sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // This exception is expected to happen
        }

        /**
         * cataloginventory/options/synchronize_with_catalog=0
         *
         * Verify the source items still exist after the delete
         */
        $sourceItems = $this->getSourceItemsBySku->execute($sku);
        self::assertNotEmpty($sourceItems);

        /**
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');
    }

    /**
     * @magentoConfigFixture default/cataloginventory/options/synchronize_with_catalog 1
     */
    public function testCancelOrderWithDeletedProductAndSyncWithCatalog()
    {
        $sku = uniqid('product_will_be_deleted');

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
        
        /**
         * Ensure consumers are cleared before next steps
         */
        $this->clearQueueProcessor->execute('inventory.source.items.cleanup');
        
        /**
         * Delete the product
         */
        $origVal = $this->registry->registry('isSecureArea');
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', true);
        $product = $this->productRepository->deleteById($sku);
        $this->registry->unregister('isSecureArea');
        $this->registry->register('isSecureArea', $origVal);

        try {
            $this->productRepository->getById($sku);
            $this->fail('This product should not be loadable: ' . $sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // This exception is expected to happen
        }
        
        /**
         * Process the source items cleanup consumers and verify the deletions worked, because
         * cataloginventory/options/synchronize_with_catalog=1
         */
        $consumer = $this->consumerFactory->get('inventory.source.items.cleanup');
        $consumer->process(1);

        $sourceItems = $this->getSourceItemsBySku->execute($sku);
        self::assertEmpty($sourceItems);

        /**
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');
    }
}
