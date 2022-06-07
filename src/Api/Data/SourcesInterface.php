<?php

namespace Ampersand\DisableStockReservation\Api\Data;

/**
 * Interface SourcesInterface
 * @package Ampersand\DisableStockReservation\Api\Data
 */
interface SourcesInterface
{
    /**
     * Order ID key constant
     */
    const ORDER_ID_KEY = 'order_id';

    /**
     * Sources key constant
     */
    const SOURCES_KEY = 'sources';

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
