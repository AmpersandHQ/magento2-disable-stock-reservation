<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Plugin\Preference\SalesInventory;

use Magento\SalesInventory\Model\Order\ReturnProcessor;
use Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;

class ProcessReturnQtyOnCreditMemoPlugin
{
    public function aroundExecute(
        ReturnProcessor     $subject,
        callable            $proceed,
        ...$args
    ): void {
        //TODO conditionally disable this plugin depending on the version of magento / codebase
        if (! \class_exists(DecrementQtyForMultipleSourceItem::class)) {
            $proceed(...$args);
        }
    }
}
