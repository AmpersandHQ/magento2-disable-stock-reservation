<?php

namespace Ampersand\DisableStockReservation\Test\Integration;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
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
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

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

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;


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
        $this->stockRegistry = $this->objectManager->get(StockRegistryInterface::class);
        $this->websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
    }


    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoCache all disabled
     * @dataProvider sourcesDataProvider
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     *
     * @throws LocalizedException
     * @throws \Exception
     *
     */
    public function testPlaceOrderAndCancelWithMsi(
        array $sourceData,
        array $expectedSourceDataAfterPlace,
        array $expectedSourceDataBeforePlace
    ) {
        $sku = $sourceData["sku"];
        $quoteItemQty = $sourceData["qty"];
        $stockId = $sourceData["stock_id"];

        $this->setStockItemConfigIsDecimal($sku, $stockId);
        $cart = $this->getCartByStockId($stockId);

        /** @var Product $product */
        $product = $this->productRepository->get($sku);

        $cartItem = $this->getCartItem($product, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);
        $this->cartRepository->save($cart);

        $this->assertEquals(1, $cart->getItemsCount(), "1 quote item should be added");
        $this->assertSourceStockBeforeOrderPlace($sku, $expectedSourceDataBeforePlace);

        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNotNull($orderId);
        $this->assertSourceStockAfterOrderPlace($sku, $expectedSourceDataAfterPlace);

        $order = $this->orderRepository->get($orderId);
        $order->cancel();
        $this->assertSourceStockAfterOrderCancel($sku, $expectedSourceDataBeforePlace);
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
     * @param int $stockId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    private function getCartByStockId(int $stockId): CartInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('reserved_order_id', 'test_order_1')
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
     * @return void
     */
    private function assertSourceStock(string $sku, array $expected): void
    {
        $sources = $this->getSources($sku);
        $this->assertEquals($expected, $sources);
    }

    /**
     * Assert source stock before placing order
     *
     * @param string $sku
     * @param array $expected
     * @return void
     */
    private function assertSourceStockBeforeOrderPlace(string $sku, array $expected): void
    {
        $this->assertSourceStock($sku, $expected);
    }

    /**
     * @param string $sku
     * @param array $expected
     * @return void
     */
    private function assertSourceStockAfterOrderPlace(string $sku, array $expected): void
    {
        $sources = $this->getSources($sku);
        $this->assertEquals($expected, $sources);
    }

    /**
     * Assert source stock after order cancel
     * Source stock should be the same as before place order
     * @param string $sku
     * @param array $expected
     * @return void
     */
    private function assertSourceStockAfterOrderCancel(string $sku, array $expected): void
    {
        $this->assertSourceStock($sku, $expected);
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
            $sources [$sourceItem->getSourceCode()] = $sourceItem->getQuantity();
        }
        return $sources;
    }

    /**
     * @return array[]
     */
    public function sourcesDataProvider(): array
    {
        return [
            [
                [
                    "sku" => "SKU-1",
                    "qty" => 8.5,
                    "stock_id" => 10
                ],
                [
                    "eu-1" => 0,
                    "eu-2" => 0,
                    "eu-3" => 10,
                    "eu-disabled" => 10
                ],
                [
                    "eu-1" => 5.5,
                    "eu-2" => 3,
                    "eu-3" => 10,
                    "eu-disabled" => 10
                ]
            ],
            [
                [
                    "sku" => "SKU-1",
                    "qty" => 2,
                    "stock_id" => 10
                ],
                [
                    "eu-1" => 3.5,
                    "eu-2" => 3,
                    "eu-3" => 10,
                    "eu-disabled" => 10
                ],
                [
                    "eu-1" => 5.5,
                    "eu-2" => 3,
                    "eu-3" => 10,
                    "eu-disabled" => 10
                ]
            ]
        ];
    }
}
