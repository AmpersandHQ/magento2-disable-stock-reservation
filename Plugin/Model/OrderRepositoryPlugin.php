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
     * OrderRepositoryPlugin constructor.
     * @param SourcesRepository $sourcesRepository
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultInterfaceFactory
     * @param SourcesConverter $sourcesConverter
     */
    public function __construct(
        SourcesRepository $sourcesRepository,
        OrderExtensionFactory $orderExtensionFactory,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultInterfaceFactory,
        SourcesConverter $sourcesConverter
    ) {
        $this->sourcesRepository = $sourcesRepository;
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->sourceSelectionResultInterfaceFactory = $sourceSelectionResultInterfaceFactory;
        $this->sourcesConverter = $sourcesConverter;
    }

    /**
     * @param OrderRepository $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterGet(OrderRepository $subject, OrderInterface $result): OrderInterface
    {
        return $this->applyExtensionAttributesToOrder($result);
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

        $orderListSources = $this->sourcesRepository->getOrderListSources($resultIds);

        $orderSources = [];
        foreach ($orderListSources as $item) {
            $orderSources[$item->getOrderId()] = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($item->getSources());
        }

        foreach ($result->getItems() as $item) {
            if (array_key_exists($orderId = $item->getEntityId(), $orderSources)) {
                if (!$extensionAttributes = $item->getExtensionAttributes()) {
                    $extensionAttributes = $this->orderExtensionFactory->create();
                }

                $extensionAttributes->setSources(
                    $orderSources[$orderId]
                );

                $item->setExtensionAttributes($extensionAttributes);
            }
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     */
    private function applyExtensionAttributesToOrder(OrderInterface $order): OrderInterface
    {
        try {
            /** @var SourceModel $sourcesModel */
            $sourcesModel = $this->sourcesRepository->getByOrderId($order->getEntityId());

            $sourceSelectionItems = $this->sourcesConverter
                ->convertSourcesJsonToSourceSelectionItems($sourcesModel->getSources());

            if (!$extensionAttributes = $order->getExtensionAttributes()) {
                $extensionAttributes = $this->orderExtensionFactory->create();
            }

            $extensionAttributes->setSources(
                $sourceSelectionItems
            );

            $order->setExtensionAttributes($extensionAttributes);
        } catch (NoSuchEntityException $exception) {
        }

        return $order;
    }
}
