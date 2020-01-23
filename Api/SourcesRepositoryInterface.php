<?php

namespace Ampersand\DisableStockReservation\Api;

use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;
use Magento\InventorySourceSelection\Model\Result\SourceSelectionItem;

/**
 * Interface SourcesRepositoryInterface
 * @package Ampersand\DisableStockReservation\Api\Data
 */
interface SourcesRepositoryInterface
{
    /**
     * @param string $orderId
     * @return SourcesInterface
     */
    public function getByOrderId(string $orderId): SourcesInterface;

    /**
     * @param SourcesInterface $model
     * @return SourcesInterface
     */
    public function save(SourcesInterface $model): SourcesInterface;

    /**
     * @param string $orderId
     * @param string $itemSku
     * @return SourceSelectionItem|null
     */
    public function getSourceItemBySku(string $orderId, string $itemSku): ?SourceSelectionItem;
}
