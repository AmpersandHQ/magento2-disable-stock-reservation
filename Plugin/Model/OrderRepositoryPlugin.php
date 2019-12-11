<?php

namespace Ampersand\DisableStockReservation\Plugin\Model;

use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Ampersand\DisableStockReservation\Model\SourcesRepository;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Ampersand\DisableStockReservation\Model\Sources as SourceModel;
use Ampersand\DisableStockReservation\Service\SourcesConverter;
use Magento\Framework\Exception\NoSuchEntityException;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources\CollectionFactory;

/**
 * Class OrderRepositoryPlugin
 * @package Ampersand\DisableStockReservation\Plugin\Model
 */
class OrderRepositoryPlugin
{
    /**
     * @var SourcesRepository
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
     * @param SourcesRepository $sourcesRepository
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultInterfaceFactory
     * @param SourcesConverter $sourcesConverter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        SourcesRepository $sourcesRepository,
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
     * @param OrderRepository $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterGet(OrderRepository $subject, OrderInterface $result): OrderInterface
    {
        try {
            /** @var SourceModel $sourcesModel */
            $sourcesModel = $this->sourcesRepository->getByOrderId($result->getEntityId());

            $sourceSelectionItems = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($sourcesModel->getSources());

            $this->applyExtensionAttributesToOrder($result, $sourceSelectionItems);
        } catch (NoSuchEntityException $exception) {
        }

        return $result;
    }

    /**
     * @param OrderRepository $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepository $subject, OrderSearchResultInterface $result): OrderSearchResultInterface
    {
        $resultIds = [];
        foreach ($result as $resultItem) {
            $resultIds[] = $resultItem->getEntityId();
        }

        $orderListSources = $this->collectionFactory->create()
            ->addFieldToFilter('order_id', ['in' => $resultIds])
            ->getItems();

        $orderSources = [];
        foreach ($orderListSources as $item) {
            $orderSources[$item->getOrderId()] = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($item->getSources());
        }

        foreach ($result->getItems() as $item) {
            if (array_key_exists($orderId = $item->getEntityId(), $orderSources)) {

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
