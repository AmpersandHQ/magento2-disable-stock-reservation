<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Sources
 * @package Ampersand\DisableStockReservation\Model
 */
class Sources extends AbstractModel
{
    public function _construct()
    {
        $this->_init(\Ampersand\DisableStockReservation\Model\ResourceModel\Sources::class);
    }
}
