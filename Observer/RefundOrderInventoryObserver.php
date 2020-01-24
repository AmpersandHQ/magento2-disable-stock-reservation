<?php

namespace Ampersand\DisableStockReservation\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Ampersand\DisableStockReservation\Service\ExecuteSourceDeductionForItems;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Class RefundOrderInventoryObserver
 * @package Ampersand\DisableStockReservation\Observer
 */
class RefundOrderInventoryObserver implements ObserverInterface
{
    /**
     * @var ExecuteSourceDeductionForItems
     */
    private $executeSourceDeductionForItems;

    /**
     * RefundOrderInventoryObserver constructor.
     * @param ExecuteSourceDeductionForItems $executeSourceDeductionForItems
     */
    public function __construct(
        ExecuteSourceDeductionForItems $executeSourceDeductionForItems
    ) {
        $this->executeSourceDeductionForItems = $executeSourceDeductionForItems;
    }

    /**
     * Return creditmemo items qty to stock
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        if (!$creditmemo instanceof CreditmemoInterface) {
            return;
        }

        $this->executeSourceDeductionForItems->executeSourceDeductionForItems($creditmemo->getOrder(), $creditmemo->getItems(), true);
    }
}
