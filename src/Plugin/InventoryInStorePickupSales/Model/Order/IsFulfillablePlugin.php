<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Plugin\InventoryInStorePickupSales\Model\Order;

use Magento\InventoryInStorePickupSales\Model\Order\IsFulfillable;

class IsFulfillablePlugin
{
    /**
     * @param IsFulfillable $subject
     * @param bool $result
     * @return bool
     */
    public function afterExecute(IsFulfillable $subject, bool $result): bool
    {
        return $result;
    }
}
