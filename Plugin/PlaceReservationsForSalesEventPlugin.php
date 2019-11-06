<?php
namespace Ampersand\DisableStockReservation\Plugin;

use Ampersand\DisableStockReservation\Model\Config;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;

class PlaceReservationsForSalesEventPlugin
{

    /**
     * Around plugin for PlaceReservationsForSalesEvent::execute function to make it do nothing.
     * This will prevent all writes to the table inventory_reservation
     *
     * @param PlaceReservationsForSalesEvent $subject
     * @param callable $proceed
     * @param $items
     * @param null $websiteId
     */
    public function aroundExecute(
        PlaceReservationsForSalesEventInterface $subject,
        callable $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ) {
        $doSomething = false;
    }
}