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
     * @return string|null
     */
    public function getOrderId(): ?string;

    /**
     * @param string $id
     * @return SourcesInterface $this
     */
    public function setOrderId(string $id): SourcesInterface;
}