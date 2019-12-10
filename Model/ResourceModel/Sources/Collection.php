<?php

namespace Ampersand\DisableStockReservation\Model\ResourceModel\Sources;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'Ampersand\DisableStockReservation\Model\Sources',
            'Ampersand\DisableStockReservation\Model\ResourceModel\Sources'
        );
    }
}
