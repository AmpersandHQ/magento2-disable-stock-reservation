<?php

namespace Ampersand\DisableStockReservation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Sources
 * @package Ampersand\DisableStockReservation\Model\ResourceModel
 */
class Sources extends AbstractDb
{
    public function _construct()
    {
        $this->_init('seraphine_order_sources', 'extension_id');
    }
}
