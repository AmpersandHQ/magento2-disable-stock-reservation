<?php

namespace Ampersand\DisableStockReservation\Api\Data;

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
}
