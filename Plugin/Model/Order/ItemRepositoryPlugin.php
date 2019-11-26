<?php

namespace Ampersand\DisableStockReservation\Plugin\Model\Order;

use Magento\Sales\Model\Order\ItemRepository;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderItemExtensionFactory;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order;
use Ampersand\DisableStockReservation\Model\GetSourceSelectionResultFromOrder;
use Ampersand\DisableStockReservation\Model\GetInventoryRequestFromOrder;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;

/**
 * Class ItemRepositoryPlugin
 * @package Ampersand\DisableStockReservation\Plugin\Model\Order
 */
class ItemRepositoryPlugin
{
    /**
     * @var OrderItemExtensionFactory
     */
    private $orderItemExtensionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var GetSourceSelectionResultFromOrder
     */
    private $sourceSelectionResult;

    /**
     * @var GetInventoryRequestFromOrder
     */
    private $getInventoryRequestFromOrder;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    /**
     * @var SourceSelectionServiceInterface
     */
    private $sourceSelectionService;

    /**
     * ItemRepositoryPlugin constructor.
     *
     * @param OrderItemExtensionFactory $orderItemExtensionFactory
     * @param LoggerInterface $logger
     * @param Order $order
     * @param GetSourceSelectionResultFromOrder $sourceSelectionResult
     * @param GetInventoryRequestFromOrder $getInventoryRequestFromOrder
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @param SourceSelectionServiceInterface $sourceSelectionService
     */
    public function __construct(
        OrderItemExtensionFactory $orderItemExtensionFactory,
        LoggerInterface $logger,
        Order $order,
        GetSourceSelectionResultFromOrder $sourceSelectionResult,
        GetInventoryRequestFromOrder $getInventoryRequestFromOrder,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        SourceSelectionServiceInterface $sourceSelectionService
    ) {
        $this->orderItemExtensionFactory = $orderItemExtensionFactory;
        $this->logger = $logger;
        $this->order = $order;
        $this->sourceSelectionResult = $sourceSelectionResult;
        $this->getInventoryRequestFromOrder = $getInventoryRequestFromOrder;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
        $this->sourceSelectionService = $sourceSelectionService;
    }

    /**
     * Set additional attributes when requesting a single order item
     *
     * @param ItemRepository  $subject
     * @param OrderItemInterface|Item $result
     * @return OrderItemInterface|Item
     */
    public function afterGet(ItemRepository $subject, OrderItemInterface $result)
    {
        return $this->applyExtensionAttributesToOrderItem($result, $result);
    }

    /**
     * Set additional attributes when requesting a list of order items
     *
     * @param ItemRepository $subject
     * @param OrderItemSearchResultInterface $result
     * @return OrderItemSearchResultInterface
     */
    public function afterGetList(ItemRepository $subject, OrderItemSearchResultInterface $result)
    {
        foreach ($result->getItems() as $item) {
            $this->applyExtensionAttributesToOrderItem($item, $result->getItems());
        }

        return $result;
    }

    /**
     * Apply extension attributes to order item
     *
     * @param OrderItemInterface $orderItem
     * @param array $allItems
     * @return OrderItemInterface
     */
    private function applyExtensionAttributesToOrderItem(OrderItemInterface $orderItem, array $allItems) : OrderItemInterface
    {
        $orderProduct = $orderItem->getProduct();

        // Ensure we do not process any further if there is no product associated and log order item
        if (!$orderProduct) {
            $this->logger->debug('Order item is missing associated product', [
                'order_id' => $orderItem->getOrderId(),
                'order_item_id' => $orderItem->getId()
            ]);

            return $orderItem;
        }

        if (!$extensionAttributes = $orderItem->getExtensionAttributes()) {
            $extensionAttributes = $this->orderItemExtensionFactory->create();
        }

        $extensionAttributes->setSourceCode(
            $this->getCustomAttributeValue($orderItem->getOrder(), $allItems)
        );

        $orderItem->setExtensionAttributes($extensionAttributes);

        return $orderItem;
    }

    /**
     * @param Order $order
     * @param array $allItems
     * @return string
     */
    private function getCustomAttributeValue(Order $order, array $allItems) : string
    {
        $inventoryRequest = $this->getInventoryRequestFromOrder->execute(
            $order,
            $this->sourceSelectionResult->getSelectionRequestItems($allItems)
        );

        $selectionAlgorithmCode = $this->getDefaultSourceSelectionAlgorithmCode->execute();
        $sourceSelectionResult = $this->sourceSelectionService->execute($inventoryRequest, $selectionAlgorithmCode);

        return $sourceSelectionResult->getSourceSelectionItems()[0]->getSourceCode();
    }
}
