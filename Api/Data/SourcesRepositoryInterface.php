<?php

namespace Ampersand\DisableStockReservation\Api\Data;

use Ampersand\DisableStockReservation\Model\Sources;

/**
 * Interface SourcesRepositoryInterface
 * @package Ampersand\DisableStockReservation\Api\Data
 */
interface SourcesRepositoryInterface
{
    /**
     * @param string $orderId
     * @return Sources
     */
    public function getByOrderId(string $orderId): Sources;

    /**
     * @param SourcesInterface $model
     * @return SourcesInterface
     */
    public function save(SourcesInterface $model): SourcesInterface;
}
