<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\Model\AbstractModel;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;

/**
 * Class Sources
 * @package Ampersand\DisableStockReservation\Model
 */
class Sources extends AbstractModel implements SourcesInterface
{
    public function _construct()
    {
        $this->_init(\Ampersand\DisableStockReservation\Model\ResourceModel\Sources::class);
    }

    public function getSources()
    {
        return $this->getData('sources');
    }

    public function setSources($sources)
    {
        return $this->setData('sources', $sources);
    }

    public function getOrderId()
    {
        return $this->getData('order_id');
    }

    public function setOrderId($id)
    {
        return $this->setData('order_id', $id);
    }
}
