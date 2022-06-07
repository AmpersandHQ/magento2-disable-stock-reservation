<?php

namespace Ampersand\DisableStockReservation\Plugin\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Ampersand\DisableStockReservation\Model\Sources as SourceModel;
use Ampersand\DisableStockReservation\Service\SourcesConverter;
use Magento\Framework\Exception\NoSuchEntityException;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources\CollectionFactory;
use Ampersand\DisableStockReservation\Api\SourcesRepositoryInterface;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;

/**
 * Class OrderRepositoryPlugin
 * @package Ampersand\DisableStockReservation\Plugin\Model
 */
class OrderRepositoryPlugin
{
    /**
     * @var SourcesRepositoryInterface
     */
    private $sourcesRepository;

    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * @var SourceSelectionResultInterfaceFactory
     */
    private $sourceSelectionResultInterfaceFactory;

    /**
     * @var SourcesConverter
     */
    private $sourcesConverter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * OrderRepositoryPlugin constructor.
     * @param SourcesRepositoryInterface $sourcesRepository
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultInterfaceFactory
     * @param SourcesConverter $sourcesConverter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        SourcesRepositoryInterface $sourcesRepository,
        OrderExtensionFactory $orderExtensionFactory,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultInterfaceFactory,
        SourcesConverter $sourcesConverter,
        CollectionFactory $collectionFactory
    ) {
        $this->sourcesRepository = $sourcesRepository;
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->sourceSelectionResultInterfaceFactory = $sourceSelectionResultInterfaceFactory;
        $this->sourcesConverter = $sourcesConverter;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * phpcs:disable Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $result): OrderInterface
    {
        try {
            /** @var SourceModel $sourcesModel */
            $sourcesModel = $this->sourcesRepository->getByOrderId($result->getId());

            $sourceSelectionItems = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($sourcesModel->getSources());

            $this->applyExtensionAttributesToOrder($result, $sourceSelectionItems);
        } catch (NoSuchEntityException $exception) {
            // Do nothing
        }

        return $result;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepositoryInterface $subject, OrderSearchResultInterface $result): OrderSearchResultInterface
    {
        $resultIds = [];
        foreach ($result->getItems() as $resultItem) {
            $resultIds[] = $resultItem->getId();
        }

        $orderListSources = $this->collectionFactory->create()
            ->addFieldToFilter(SourcesInterface::ORDER_ID_KEY, ['in' => $resultIds])
            ->getItems();

        $orderSources = [];
        /** @var SourceModel $orderSourcesItem */
        foreach ($orderListSources as $orderSourcesItem) {
            $orderSources[$orderSourcesItem->getOrderId()] = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($orderSourcesItem->getSources());
        }

        foreach ($result->getItems() as $item) {
            if (array_key_exists($orderId = $item->getId(), $orderSources)) {
                $this->applyExtensionAttributesToOrder($item, $orderSources[$orderId]);
            }
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     */
    private function applyExtensionAttributesToOrder(OrderInterface $order, $sourceSelectionItems): OrderInterface
    {
        if (!$extensionAttributes = $order->getExtensionAttributes()) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        $extensionAttributes->setSources(
            $sourceSelectionItems
        );

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
