<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Model\SourceDeductionService;

use Ampersand\DisableStockReservation\Model\SourceItem\Command\DecrementSourceItemQtyFactory;
use Magento\CatalogInventory\Model\Configuration;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;

class PatchedSourceDeductionService implements SourceDeductionServiceInterface
{
    /**
     * Constant for zero stock quantity value.
     */
    private const ZERO_STOCK_QUANTITY = 0.0;

    /**
     * @var Configuration
     */
    private $stockConfiguration;

    /**
     * @var GetSourceItemBySourceCodeAndSku
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var DecrementSourceItemQtyFactory
     */
    private $decrementSourceItemFactory;

    /**
     * @param GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param DecrementSourceItemQtyFactory $decrementSourceItemFactory
     * @param Configuration $stockConfiguration
     */
    public function __construct(
        GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        DecrementSourceItemQtyFactory $decrementSourceItemFactory,
        Configuration $stockConfiguration
    ) {
        $this->getSourceItemBySourceCodeAndSku = $getSourceItemBySourceCodeAndSku;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->decrementSourceItemFactory = $decrementSourceItemFactory;
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * @inheritdoc
     */
    public function execute(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $sourceCode = $sourceDeductionRequest->getSourceCode();
        $salesChannel = $sourceDeductionRequest->getSalesChannel();
        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $sourceItemDecrementData = [];
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $itemSku = $item->getSku();
            $qty = $item->getQty();
            $stockItemConfiguration = $this->getStockItemConfiguration->execute(
                $itemSku,
                $stockId
            );

            if (!$stockItemConfiguration->isManageStock()) {
                //We don't need to Manage Stock
                continue;
            }

            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $itemSku);

            /*
             * Ampersand change start
             *
             * Fix a problem when a product has a large negative quantity, and an order with that product is canceled
             * from backend.
            */
            $salesEvent = $sourceDeductionRequest->getSalesEvent();
            if ($salesEvent->getType() ==
                \Magento\InventorySalesApi\Api\Data\SalesEventInterface::EVENT_ORDER_CANCELED) {
                if (($sourceItem->getQuantity() - $qty) < 0) {
                    $sourceItem->setQuantity($sourceItem->getQuantity() - $qty);
                    $stockStatus = $this->getSourceStockStatus(
                        $stockItemConfiguration,
                        $sourceItem
                    );
                    $sourceItem->setStatus($stockStatus);
                    continue;
                }
            }
            /*
             * Ampersand change finish
             */

            if (($sourceItem->getQuantity() - $qty) >= 0) {
                $sourceItem->setQuantity($sourceItem->getQuantity() - $qty);
                $stockStatus = $this->getSourceStockStatus(
                    $stockItemConfiguration,
                    $sourceItem
                );
                $sourceItem->setStatus($stockStatus);
                $sourceItemDecrementData[] = [
                    'source_item' => $sourceItem,
                    'qty_to_decrement' => $qty
                ];
            } else {
                throw new LocalizedException(
                    __('Not all of your products are available in the requested quantity.')
                );
            }
        }

        if (!empty($sourceItemDecrementData)) {
            $this->decrementSourceItemFactory->create()->execute($sourceItemDecrementData);
        }
    }

    /**
     * Get source item stock status after quantity deduction.
     *
     * @param StockItemConfigurationInterface $stockItemConfiguration
     * @param SourceItemInterface $sourceItem
     *
     * @return int
     */
    private function getSourceStockStatus(
        StockItemConfigurationInterface $stockItemConfiguration,
        SourceItemInterface $sourceItem
    ): int {
        $sourceItemQty = $sourceItem->getQuantity() ?: self::ZERO_STOCK_QUANTITY;
        $currentStatus = (int)$stockItemConfiguration->getExtensionAttributes()->getIsInStock();
        $calculatedStatus =  SourceItemInterface::STATUS_IN_STOCK;

        if ($sourceItemQty === $stockItemConfiguration->getMinQty() && !$stockItemConfiguration->getBackorders()) {
            $calculatedStatus = SourceItemInterface::STATUS_OUT_OF_STOCK;
        }

        if ($this->stockConfiguration->getCanBackInStock() && $sourceItemQty > $stockItemConfiguration->getMinQty()
            && $currentStatus === SourceItemInterface::STATUS_OUT_OF_STOCK
        ) {
            return SourceItemInterface::STATUS_IN_STOCK;
        }

        return $currentStatus === SourceItemInterface::STATUS_OUT_OF_STOCK ? $currentStatus : $calculatedStatus;
    }
}
