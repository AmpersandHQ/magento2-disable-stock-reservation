<?php

namespace Ampersand\DisableStockReservation\Api\Data;

/**
 * Interface SourcesInterface
 * @package Ampersand\DisableStockReservation\Api\Data
 */
interface SourcesInterface
{
    /**
     * @return string|null
     */
    public function getSources(): ?string;

    /**
     * @param string $sources
     * @return SourcesInterface $this
     */
    public function setSources(string $sources): SourcesInterface;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * @param int $id
     * @return SourcesInterface $this
     */
    public function setOrderId(int $id): SourcesInterface;
}
