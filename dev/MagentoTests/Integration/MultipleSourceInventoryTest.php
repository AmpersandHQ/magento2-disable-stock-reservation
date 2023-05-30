<?php

namespace Ampersand\DisableStockReservation\Test\Integration;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Inventory\Model\SourceItem\Command\GetSourceItemsBySku;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryApi\Model\GetSourceCodesBySkusInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveStockItemConfigurationInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySales\Model\GetStockBySalesChannelCache;

class MultipleSourceInventoryTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var GetSourceItemsBySku
     */
    private $getSourceItemsBySku;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var SaveStockItemConfigurationInterface
     */
    private $saveStockItemConfiguration;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var CartItemInterfaceFactory
     */
    private $cartItemFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->getSourceItemsBySku = $this->objectManager->get(GetSourceItemsBySku::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->getStockItemConfiguration = $this->objectManager->get(GetStockItemConfigurationInterface::class);
        $this->saveStockItemConfiguration = $this->objectManager->get(SaveStockItemConfigurationInterface::class);
        $this->storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);
        $this->cartItemFactory = $this->objectManager->get(CartItemInterfaceFactory::class);
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->cartManagement = $this->objectManager->get(CartManagementInterface::class);
        $this->orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
    }

    /**
     *
     * @dataProvider sourcesDataProvider
     *
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     *
     * @throws LocalizedException
     * @throws \Exception
     */
    public function testPlaceOrderAndCancelWithMsi(
        array $sourceData,
        array $expectedSourceDataAfterPlace,
        array $expectedSourceDataBeforePlace,
        bool $expectException
    ) {
        $sku = $sourceData["sku"];
        $quoteItemQty = $sourceData["qty"];
        $stockId = $sourceData["stock_id"];

        if ($expectException) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage("The requested qty is not available");
        }
        /*
         * Additional magento and product configuration
         */
        $this->setStockItemConfigIsDecimal($sku, $stockId);
        $this->clearSalesChannelCache();

        /*
         * Verify the stock is as expected before any interactions
         */
        $this->assertSourceStock(
            $sku,
            $expectedSourceDataBeforePlace,
            'Stock does not match what is expected before adding to basket'
        );

        /*
         * Add to basket and assert the source data has not changed
         */
        $cart = $this->getCart();
        /** @var Product $product */
        $product = $this->productRepository->get($sku);

        $cartItem = $this->getCartItem($product, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);
        $this->cartRepository->save($cart);

        $this->assertEquals(1, $cart->getItemsCount(), "1 quote item should be added");
        $this->assertSourceStock(
            $sku,
            $expectedSourceDataBeforePlace,
            'Stock does not match what is expected before placing order'
        );

        /*
         * Place the order and assert the source data has been reduced
         */
        $orderId = $this->cartManagement->placeOrder($cart->getId());
        self::assertNotNull($orderId);
        $this->assertSourceStock(
            $sku,
            $expectedSourceDataAfterPlace,
            'Stock does not match what is expected after placing order'
        );

        /*
         * Cancel the order and assert the source data has been returned correctly
         */
        $order = $this->orderRepository->get($orderId);
        $order->cancel();
        $this->assertSourceStock(
            $sku,
            $expectedSourceDataBeforePlace,
            'Stock does not match what is after cancelling order'
        );
    }

    /**
     * @param string $sku
     * @param int $stockId
     */
    private function setStockItemConfigIsDecimal(string $sku, int $stockId): void
    {
        $stockItemConfiguration = $this->getStockItemConfiguration->execute($sku, $stockId);
        $stockItemConfiguration->setIsQtyDecimal(true);
        $this->saveStockItemConfiguration->execute($sku, $stockId, $stockItemConfiguration);
    }

    /**
     * Clear the GetStockBySalesChannelCache as it gets populated during fixture runtime and varies depending on the
     * version of magento being tested.
     *
     * This way we can start our test with a clear cache after all the fixtures have run.
     *
     * @return void
     * @throws \ReflectionException
     */
    private function clearSalesChannelCache(): void
    {
        if (class_exists(GetStockBySalesChannelCache::class)) {
            $getStockBySalesChannelCache = $this->objectManager->get(GetStockBySalesChannelCache::class);
            $ref = new \ReflectionObject($getStockBySalesChannelCache);
            try {
                $refProperty = $ref->getProperty('channelCodes');
            } catch (\ReflectionException $exception) {
                $refProperty = $ref->getParentClass()->getProperty('channelCodes');
            }
            $refProperty->setAccessible(true);
            $refProperty->setValue($getStockBySalesChannelCache, []);
        }

        $stockId = $this->objectManager->get(StockResolverInterface::class)
            ->execute(SalesChannelInterface::TYPE_WEBSITE, 'eu_website')
            ->getStockId();
        $this->assertEquals(10, $stockId, 'The stock id for the eu_website should be 10');
    }

    /**
     * @param ProductInterface $product
     * @param float $quoteItemQty
     * @param int $cartId
     * @return CartItemInterface
     */
    private function getCartItem(ProductInterface $product, float $quoteItemQty, int $cartId): CartItemInterface
    {
        /** @var CartItemInterface $cartItem */
        $cartItem =
            $this->cartItemFactory->create(
                [
                    'data' => [
                        CartItemInterface::KEY_SKU => $product->getSku(),
                        CartItemInterface::KEY_QTY => $quoteItemQty,
                        CartItemInterface::KEY_QUOTE_ID => $cartId,
                        'product_id' => $product->getId(),
                        'product' => $product
                    ]
                ]
            );
        return $cartItem;
    }

    /**
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    private function getCart(): CartInterface
    {
        // test_order_1 is set in vendor/magento/module-inventory-sales-api/Test/_files/quote.php
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('reserved_order_id', 'test_order_1')
            ->setPageSize(1)
            ->create();
        /** @var CartInterface $cart */
        $cart = current($this->cartRepository->getList($searchCriteria)->getItems());
        $storeCode = 'store_for_eu_website';

        /** @var StoreInterface $store */
        $store = $this->storeRepository->get($storeCode);
        $this->storeManager->setCurrentStore($store->getId());
        $cart->setStoreId($store->getId());
        return $cart;
    }

    /**
     *
     * @param string $sku
     * @param array $expected
     * @param string $message
     * @return void
     */
    private function assertSourceStock(string $sku, array $expected, string $message = ''): void
    {
        $sources = $this->getSources($sku);
        $this->assertEquals($expected, $sources, $message);
    }

    /**
     * Get source items by sku
     * @param string $sku
     * @return array
     */
    private function getSources(string $sku): array
    {
        $sources = [];
        $sourceItems = $this->getSourceItemsBySku->execute($sku);
        foreach ($sourceItems as $sourceItem) {
            $sources[$sourceItem->getSourceCode()] = $sourceItem->getQuantity();
        }
        return $sources;
    }

    /**
     * @return array[]
     */
    public function sourcesDataProvider(): array
    {
        return [
            'purchase 8.5 from eu-1 and eu-2, then return on cancel' => [
                'purchase_data' => [
                    "sku" => "SKU-1",
                    "qty" => 8.5,
                    "stock_id" => 10
                ],
                'expected_source_data_after_place' => [
                    "eu-1" => 0,
                    "eu-2" => 0,
                    "eu-3" => 10.0,
                    "eu-disabled" => 10.0,
                ],
                'expected_source_data_before_place' => [
                    "eu-1" => 5.5,
                    "eu-2" => 3.0,
                    "eu-3" => 10.0,
                    "eu-disabled" => 10.0,
                ],
                false
            ],
            'purchase 2 from eu-1, then return on cancel' => [
                'purchase_data' => [
                    "sku" => "SKU-1",
                    "qty" => 2.0,
                    "stock_id" => 10
                ],
                'expected_source_data_after_place' => [
                    "eu-1" => 3.5,
                    "eu-2" => 3,
                    "eu-3" => 10,
                    "eu-disabled" => 10,
                ],
                'expected_source_data_before_place' => [
                    "eu-1" => 5.5,
                    "eu-2" => 3.0,
                    "eu-3" => 10.0,
                    "eu-disabled" => 10.0,
                ],
                false
            ],
            'purchase 18.5 from eu-1 and eu-2 and eu-3, then expect qty unavailable' => [
                'purchase_data' => [
                    "sku" => "SKU-1",
                    "qty" => 18.5,
                    "stock_id" => 10
                ],
                'expected_source_data_after_place' => [],
                'expected_source_data_before_place' => [
                    "eu-1" => 5.5,
                    "eu-2" => 3.0,
                    "eu-3" => 10.0,
                    "eu-disabled" => 10.0,
                ],
                true
            ],
            'Test cannot add out of stock and disabled source to cart' => [
                'purchase_data' => [
                    "sku" => "SKU-1",
                    "qty" => 25,
                    "stock_id" => 10
                ],
                'expected_source_data_after_place' => [],
                'expected_source_data_before_place' => [
                    "eu-1" => 5.5,
                    "eu-2" => 3.0,
                    "eu-3" => 10.0,
                    "eu-disabled" => 10.0,
                ],
                true
            ]
        ];
    }
}
