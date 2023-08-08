<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Plugin\Preference\SalesInventory;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesInventory\Model\Order\ReturnProcessor;
use Magento\InventorySales\Model\GetBackorder;
use Magento\InventorySales\Plugin\SalesInventory\ProcessReturnQtyOnCreditMemoPlugin as CorePlugin;

class ProcessReturnQtyOnCreditMemoPlugin extends CorePlugin
{
    /**
     * Conditionally disable process_return_product_qty_on_credit_memo plugin
     *
     * It does additional stock replenishment on creditmemo in 2.4.5 and above, we have already covered that case
     *
     * @see \Ampersand\DisableStockReservation\Observer\RestoreSourceItemQuantityOnRefundObserver
     * @link https://github.com/magento/inventory/pull/2896
     *
     * @param ReturnProcessor $subject
     * @param callable $proceed
     * @param CreditmemoInterface $creditmemo
     * @param OrderInterface $order
     * @param array $returnToStockItems
     * @param bool $isAutoReturn
     * @return void
     */
    public function aroundExecute(
        ReturnProcessor $subject,
        callable $proceed,
        CreditmemoInterface $creditmemo,
        OrderInterface $order,
        array $returnToStockItems = [],
        bool $isAutoReturn = false
    ): void {
        // GetBackOrder class only available in 2.4.5 and higher, we need to disable this plugin on those versions
        if (\class_exists(GetBackorder::class)) {
            return;
        }
        parent::aroundExecute($subject, $proceed, $creditmemo, $order, $returnToStockItems, $isAutoReturn);
    }
}
