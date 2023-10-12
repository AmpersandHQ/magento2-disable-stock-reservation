<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\Cancel;

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

class CancelOrderTest extends TestCase
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

    public function testCancelOrderBaseline()
    {
        $sku = uniqid('cancel_order_baseline');

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
         * Cancel the order
         */
        $this->assertTrue($order->canCancel(), 'Cannot cancel the order');
        $order->cancel();
        $this->assertEquals('canceled', $order->getStatus(), 'The order was not cancelled');
    }
}
