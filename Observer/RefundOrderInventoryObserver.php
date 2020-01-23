<?php

namespace Ampersand\DisableStockReservation\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Ampersand\DisableStockReservation\Service\ExecuteSourceDeductionForItems;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Class RefundOrderInventoryObserver
 * @package Ampersand\DisableStockReservation\Observer
 */
class RefundOrderInventoryObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ExecuteSourceDeductionForItems
     */
    private $executeSourceDeductionForItems;

    /**
     * RefundOrderInventoryObserver constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param ExecuteSourceDeductionForItems $executeSourceDeductionForItems
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ExecuteSourceDeductionForItems $executeSourceDeductionForItems
    ) {
        $this->orderRepository = $orderRepository;
        $this->executeSourceDeductionForItems = $executeSourceDeductionForItems;
    }

    /**
     * Return creditmemo items qty to stock
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /* @var Creditmemo $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        /** @var OrderInterface $order */
        $order = $this->orderRepository->get($creditmemo->getOrderId());

        $this->executeSourceDeductionForItems->executeSourceDeductionForItems($order, $creditmemo->getItems(), $creditmemo);
    }
}
