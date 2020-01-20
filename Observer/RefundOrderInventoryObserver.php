<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ampersand\DisableStockReservation\Observer;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\SalesInventory\Model\Order\ReturnProcessor;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Ampersand\DisableStockReservation\Model\GetSourceSelectionResultFromOrder;
use Magento\InventorySourceDeductionApi\Model\ItemToDeductFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionService;

/**
 * Catalog inventory module observer
 * @deprecated 100.2.0
 */
class RefundOrderInventoryObserver implements ObserverInterface
{
    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @var StockManagementInterface
     */
    private $stockManagement;

    /**
     * @var \Magento\CatalogInventory\Model\Indexer\Stock\Processor
     */
    private $stockIndexerProcessor;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price\Processor
     */
    private $priceIndexer;

    /**
     * @var \Magento\SalesInventory\Model\Order\ReturnProcessor
     */
    private $returnProcessor;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var GetSourceSelectionResultFromOrder
     */
    private $getSourceSelectionResultFromOrder;

    /**
     * @var ItemToDeductFactory
     */
    private $itemToDeductFactory;

    /**
     * @var SourceDeductionRequestFactory
     */
    private $sourceDeductionRequestFactory;

    /**
     * @var SourceDeductionService
     */
    private $sourceDeductionService;

    /**
     * RefundOrderInventoryObserver constructor.
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockManagementInterface $stockManagement
     * @param \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexerProcessor
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer
     * @param ReturnProcessor $returnProcessor
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param WebsiteRepositoryInterface $websiteRepository,
     * @param SalesEventInterfaceFactory $salesEventFactory,
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder
     * @param ItemToDeductFactory $itemToDeductFactory
     * @param SourceDeductionRequestFactory $sourceDeductionRequestFactory
     * @param SourceDeductionService $sourceDeductionService
     */
    public function __construct(
        StockConfigurationInterface $stockConfiguration,
        StockManagementInterface $stockManagement,
        \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexerProcessor,
        \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer,
        \Magento\SalesInventory\Model\Order\ReturnProcessor $returnProcessor,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        WebsiteRepositoryInterface $websiteRepository,
        SalesEventInterfaceFactory $salesEventFactory,
        SalesChannelInterfaceFactory $salesChannelFactory,
        GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder,
        ItemToDeductFactory $itemToDeductFactory,
        SourceDeductionRequestFactory $sourceDeductionRequestFactory,
        SourceDeductionService $sourceDeductionService
    ) {
        $this->stockConfiguration = $stockConfiguration;
        $this->stockManagement = $stockManagement;
        $this->stockIndexerProcessor = $stockIndexerProcessor;
        $this->priceIndexer = $priceIndexer;
        $this->returnProcessor = $returnProcessor;
        $this->orderRepository = $orderRepository;
        $this->websiteRepository = $websiteRepository;
        $this->salesEventFactory = $salesEventFactory;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->getSourceSelectionResultFromOrder = $getSourceSelectionResultFromOrder;
        $this->itemToDeductFactory = $itemToDeductFactory;
        $this->sourceDeductionRequestFactory = $sourceDeductionRequestFactory;
        $this->sourceDeductionService = $sourceDeductionService;
    }

    /**
     * Return creditmemo items qty to stock
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /* @var $creditmemo \Magento\Sales\Model\Order\Creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $this->orderRepository->get($creditmemo->getOrderId());
        $returnToStockItems = [];

        $websiteId = $order->getStore()->getWebsiteId();
        $websiteCode = $this->websiteRepository->getById($websiteId)->getCode();
        $salesChannel = $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $websiteCode
            ]
        ]);

        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_CREDITMEMO_CREATED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => (string)$creditmemo->getOrderId()
        ]);

        $sourceSelectionItems = $this->getSourceSelectionResultFromOrder->execute($order)->getSourceSelectionItems();

        foreach ($creditmemo->getItems() as $item) {
            if ($item->getBackToStock()) {
                $returnToStockItems[] = $item->getOrderItemId();

                foreach ($sourceSelectionItems as $sourceSelectionItem) {
                    if ($sourceSelectionItem->getSku() === $item->getSku()) {
                        $sourceCode = $sourceSelectionItem->getSourceCode();

                        $sourceDeductionRequest = $this->sourceDeductionRequestFactory->create([
                            'sourceCode' => $sourceCode,
                            'items' => [$this->itemToDeductFactory->create([
                                'sku' => $item->getSku(),
                                'qty' => -$item->getQty()
                            ])],
                            'salesChannel' => $salesChannel,
                            'salesEvent' => $salesEvent
                        ]);

                        $this->sourceDeductionService->execute($sourceDeductionRequest);
                        break;
                    }
                }
                $this->priceIndexer->reindexRow($item->getProductId());
            }
        }
        if (!empty($returnToStockItems)) {
            $this->returnProcessor->execute($creditmemo, $order, $returnToStockItems);
        }
    }
}
