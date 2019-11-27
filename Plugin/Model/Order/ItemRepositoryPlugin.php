<?php

namespace Ampersand\DisableStockReservation\Plugin\Model\Order;

use Magento\Sales\Model\Order\ItemRepository;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderItemExtensionFactory;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
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
     * @param GetSourceSelectionResultFromOrder $sourceSelectionResult
     * @param GetInventoryRequestFromOrder $getInventoryRequestFromOrder
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @param SourceSelectionServiceInterface $sourceSelectionService
     */
    public function __construct(
        OrderItemExtensionFactory $orderItemExtensionFactory,
        GetSourceSelectionResultFromOrder $sourceSelectionResult,
        GetInventoryRequestFromOrder $getInventoryRequestFromOrder,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        SourceSelectionServiceInterface $sourceSelectionService
    ) {
        $this->orderItemExtensionFactory = $orderItemExtensionFactory;
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
        if (!$extensionAttributes = $orderItem->getExtensionAttributes()) {
            $extensionAttributes = $this->orderItemExtensionFactory->create();
        }

        $extensionAttributes->setSourceCode(
            $this->getOrderSourceCode($orderItem->getOrder(), $allItems)
        );

        $orderItem->setExtensionAttributes($extensionAttributes);

        return $orderItem;
    }

    /**
     * @TODO what happens if there's more than one source?
     *
     * @param Order $order
     * @param array $allItems
     * @return string|null
     */
    private function getOrderSourceCode(Order $order, array $allItems) : ?string
    {
        $inventoryRequest = $this->getInventoryRequestFromOrder->execute(
            $order,
            $this->sourceSelectionResult->getSelectionRequestItems($allItems)
        );

        $selectionAlgorithmCode = $this->getDefaultSourceSelectionAlgorithmCode->execute();
        $sourceSelectionResult = $this->sourceSelectionService->execute($inventoryRequest, $selectionAlgorithmCode);

        if (empty($sourceSelectionResult)) {
            return null;
        }

        return $sourceSelectionResult->getSourceSelectionItems()[0]->getSourceCode();
    }
}
