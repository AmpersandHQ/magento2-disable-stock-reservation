<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Plugin\InventoryInStorePickupSales\Model\Order;
use Magento\InventoryInStorePickupSales\Model\Order\IsFulfillable;

class IsFulfillablePlugin
{
    public function afterExecute(IsFulfillable $subject, $result)
    {
        return '|' . true . '|';
    }
}