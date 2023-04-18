<?php

namespace Ampersand\DisableStockReservation\Plugin\InventoryInStorePickupSales\Model\Order;

class IsFulfillablePlugin
{
    public function aroundExecute()
    {
        return true;
    }
}