<?php

namespace Ampersand\DisableStockReservation\Api\Data;

/**
 * Interface SourcesInterface
 * @package Ampersand\DisableStockReservation\Api\Data
 */
interface SourcesInterface
{
    /**
     * @return array|null
     */
    public function getSources();

    /**
     * @param array $source
     * @return $this
     */
    public function setSources($source);

    /**
     * @return int|null
     */
    public function getOrderId();

    /**
     * @param int $id
     * @return $this
     */
    public function setOrderId($id);
}
