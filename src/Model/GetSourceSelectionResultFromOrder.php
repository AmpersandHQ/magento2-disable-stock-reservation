<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\App\ObjectManager;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Model\GetSkuFromOrderItemInterface;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class GetSourceSelectionResultFromOrder
{
    /**
     * @var GetSkuFromOrderItemInterface
     */
    private $getSkuFromOrderItem;

    /**
     * @var ItemRequestInterfaceFactory
     */
    private $itemRequestFactory;

    /**
     * @var GetDefaultSourceSelectionAlgorithmCodeInterface
     */
    private $getDefaultSourceSelectionAlgorithmCode;

    /**
     * @var SourceSelectionServiceInterface
     */
    private $sourceSelectionService;

    /**
     * @var GetInventoryRequestFromOrder
     */
    private $getInventoryRequestFromOrder;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    private $isSourceItemManagementAllowedForProductType;

    /**
     * @var GetProductTypesBySkusInterface
     */
    private $getProductTypesBySkus;

    /**
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param GetSkuFromOrderItemInterface $getSkuFromOrderItem
     * @param ItemRequestInterfaceFactory $itemRequestFactory
     * @param GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode
     * @param SourceSelectionServiceInterface $sourceSelectionService
     * @param GetInventoryRequestFromOrder|null $getInventoryRequestFromOrder
     * @param GetProductTypesBySkusInterface|null $getProductTypesBySkus
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        GetSkuFromOrderItemInterface $getSkuFromOrderItem,
        ItemRequestInterfaceFactory $itemRequestFactory,
        GetDefaultSourceSelectionAlgorithmCodeInterface $getDefaultSourceSelectionAlgorithmCode,
        SourceSelectionServiceInterface $sourceSelectionService,
        GetInventoryRequestFromOrder $getInventoryRequestFromOrder = null,
        GetProductTypesBySkusInterface $getProductTypesBySkus = null
    ) {
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->itemRequestFactory = $itemRequestFactory;
        $this->getDefaultSourceSelectionAlgorithmCode = $getDefaultSourceSelectionAlgorithmCode;
        $this->sourceSelectionService = $sourceSelectionService;
        $this->getSkuFromOrderItem = $getSkuFromOrderItem;
        $this->getInventoryRequestFromOrder = $getInventoryRequestFromOrder ?:
            ObjectManager::getInstance()->get(GetInventoryRequestFromOrder::class);
        $this->getProductTypesBySkus = $getProductTypesBySkus ?:
            ObjectManager::getInstance()->get(GetProductTypesBySkusInterface::class);
    }

    /**
     * @param OrderInterface $order
     * @return SourceSelectionResultInterface
     */
    public function execute(OrderInterface $order): SourceSelectionResultInterface
    {
        /** @var OrderInterface $order */
        $inventoryRequest = $this->getInventoryRequestFromOrder->execute(
            $order,
            $this->getSelectionRequestItems($order->getItems())
        );

        $selectionAlgorithmCode = $this->getDefaultSourceSelectionAlgorithmCode->execute();
        return $this->sourceSelectionService->execute($inventoryRequest, $selectionAlgorithmCode);
    }

    /**
     * Get selection request items
     *
     * @param OrderItemInterface[] $orderItems
     * @return array
     */
    private function getSelectionRequestItems($orderItems): array
    {
        $selectionRequestItems = [];
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        foreach ($orderItems as $orderItem) {
            if (!$this->isDeductible($orderItem)) {
                continue;
            }

            $itemSku = $this->getSkuFromOrderItem->execute($orderItem);
            $qty = $this->castQty($orderItem, $orderItem->getQtyOrdered());

            $selectionRequestItems[] = $this->itemRequestFactory->create([
                'sku' => $itemSku,
                'qty' => $qty,
            ]);
        }

        return $this->groupItemsBySku($selectionRequestItems);
    }

    private function isDeductible(OrderItemInterface $orderItem): bool
    {
        $productType = $orderItem->getProduct() ? $orderItem->getProduct()->getTypeId() : '';
        return $productType && $this->isSourceItemManagementAllowedForProductType->execute($productType);
    }

    private function groupItemsBySku(array $orderItems): array
    {
        $processingItems = $groupedItems = [];
        foreach ($orderItems as $orderItem) {
            if (empty($processingItems[$orderItem->getSku()])) {
                $processingItems[$orderItem->getSku()] = $orderItem->getQty();
            } else {
                $processingItems[$orderItem->getSku()] += $orderItem->getQty();
            }
        }

        foreach ($processingItems as $sku => $qty) {
            $groupedItems[] = $this->itemRequestFactory->create([
                'sku' => $sku,
                'qty' => $qty
            ]);
        }

        return $groupedItems;
    }

    /**
     * Cast qty value
     *
     * @param OrderItemInterface $item
     * @param string|int|float $qty
     * @return float
     */
    private function castQty(OrderItemInterface $item, $qty): float
    {
        if ($item->getIsQtyDecimal()) {
            $qty = (float)$qty;
        } else {
            $qty = (int)$qty;
        }

        return $qty > 0 ? $qty : 0;
    }
}
