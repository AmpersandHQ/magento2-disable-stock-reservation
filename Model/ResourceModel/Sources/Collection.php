<?php

namespace Ampersand\DisableStockReservation\Model\ResourceModel\Sources;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ampersand\DisableStockReservation\Model\Sources;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources as ResourceModelSources;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;

class Collection extends AbstractCollection
{
    /**
     * Collection items
     *
     * @var SourcesInterface[]
     */
    protected $_items = [];

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Sources::class, ResourceModelSources::class);
    }

    /**
     * Retrieve collection items
     *
     * @return SourcesInterface[]
     */
    public function getItems()
    {
        $this->load();
        return $this->_items;
    }
}
