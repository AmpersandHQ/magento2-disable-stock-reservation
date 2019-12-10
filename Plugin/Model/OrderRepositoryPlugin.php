<?php

namespace Ampersand\DisableStockReservation\Plugin\Model;

use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Ampersand\DisableStockReservation\Model\GetSourceSelectionResultFromOrder;
use Magento\Sales\Api\Data\OrderExtensionFactory;

/**
 * Class OrderRepositoryPlugin
 * @package Ampersand\DisableStockReservation\Plugin\Model
 */
class OrderRepositoryPlugin
{
    /**
     * @var GetSourceSelectionResultFromOrder
     */
    private $getSourceSelectionResultFromOrder;

    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * OrderRepositoryPlugin constructor.
     * @param GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder
     * @param OrderExtensionFactory $orderExtensionFactory
     */
    public function __construct(
        GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder,
        OrderExtensionFactory $orderExtensionFactory
    ) {
        $this->getSourceSelectionResultFromOrder = $getSourceSelectionResultFromOrder;
        $this->orderExtensionFactory = $orderExtensionFactory;
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
        foreach ($result->getItems() as $item) {
            $this->applyExtensionAttributesToOrder($item);
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     * @return OrderInterface
     */
    private function applyExtensionAttributesToOrder(OrderInterface $order): OrderInterface
    {
        $sourceSelectionResult = $this->getSourceSelectionResultFromOrder->execute($order);

        if (!$extensionAttributes = $order->getExtensionAttributes()) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        $extensionAttributes->setSources(
            $sourcesItems = $sourceSelectionResult->getSourceSelectionItems()
        );

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
