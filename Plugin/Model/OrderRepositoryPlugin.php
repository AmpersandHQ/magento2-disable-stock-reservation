<?php

namespace Ampersand\DisableStockReservation\Plugin\Model;

use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Ampersand\DisableStockReservation\Model\SourcesRepository;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Ampersand\DisableStockReservation\Service\SourcesConverter;

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
        return $this->applyExtensionAttributesToOrder($result, null);
    }

    /**
     * @param OrderRepository $subject
     * @param OrderSearchResultInterface $result
     * @return OrderSearchResultInterface
     */
    public function afterGetList(OrderRepository $subject, OrderSearchResultInterface $result): OrderSearchResultInterface
    {
        $sourcesCollection = $this->sourcesRepository->getOrdersSourcesCollection();

        foreach ($result->getItems() as $item) {
            $sourceSelectionItems = $this->sourcesConverter
                ->convertSourcesArrayToSourceSelectionItems($sourcesCollection[$item->getEntityId()]);

            $sourceSelectionResult = $this->sourceSelectionResultInterfaceFactory->create([
                'sourceItemSelections' => $sourceSelectionItems,
                'isShippable' => true
            ]);

            $this->applyExtensionAttributesToOrder($item, $sourceSelectionResult);
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @param SourceSelectionResultInterface|null $sourceSelectionResult
     * @return OrderInterface
     */
    private function applyExtensionAttributesToOrder(
        OrderInterface $order,
        SourceSelectionResultInterface $sourceSelectionResult = null
    ): OrderInterface
    {
        if ($sourceSelectionResult === null) {
            $sourceSelectionResult = $this->sourcesRepository->getSourceSelectionResultByOrderId($order->getEntityId());
        }

        if (!$extensionAttributes = $order->getExtensionAttributes()) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        $extensionAttributes->setSources(
            $sourcesItems = $sourceSelectionResult ? $sourceSelectionResult->getSourceSelectionItems() : null
        );

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
