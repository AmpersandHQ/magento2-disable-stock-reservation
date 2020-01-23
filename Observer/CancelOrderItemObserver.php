<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventorySales\Model\GetItemsToCancelFromOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Api\Data\OrderInterface;
use Ampersand\DisableStockReservation\Service\ExecuteSourceDeductionForItems;

/**
 * Class CancelOrderItemObserver
 * @package Ampersand\DisableStockReservation\Observer
 */
class CancelOrderItemObserver implements ObserverInterface
{
    /**
     * @var GetItemsToCancelFromOrderItem
     */
    private $getItemsToCancelFromOrderItem;

    /**
     * @var ExecuteSourceDeductionForItems
     */
    private $executeSourceDeductionForItems;


    /**
     * CancelOrderItemObserver constructor.
     * @param GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem
     * @param ExecuteSourceDeductionForItems $executeSourceDeductionForItems
     */
    public function __construct(
        GetItemsToCancelFromOrderItem $getItemsToCancelFromOrderItem,
        ExecuteSourceDeductionForItems $executeSourceDeductionForItems
    ) {
        $this->getItemsToCancelFromOrderItem = $getItemsToCancelFromOrderItem;
        $this->executeSourceDeductionForItems = $executeSourceDeductionForItems;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        /** @var OrderItem $item */
        $orderItem = $observer->getEvent()->getItem();

        $itemsToCancel = $this->getItemsToCancelFromOrderItem->execute($orderItem);

        if (empty($itemsToCancel)) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $orderItem->getOrder();

        $this->executeSourceDeductionForItems->executeSourceDeductionForItems($order, $itemsToCancel);
    }
}
