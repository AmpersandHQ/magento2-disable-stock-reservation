<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Observer;

use Ampersand\DisableStockReservation\Model\GetSourceSelectionResultFromOrder;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventoryShipping\Model\SourceDeductionRequestsFromSourceSelectionFactory;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Ampersand\DisableStockReservation\Model\SourcesRepository;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Ampersand\DisableStockReservation\Service\SourcesConverter;
use Ampersand\DisableStockReservation\Model\SourcesFactory;

class SourceDeductionProcessor implements ObserverInterface
{
    /**
     * @var GetSourceSelectionResultFromOrder
     */
    private $getSourceSelectionResultFromOrder;

    /**
     * @var SourceDeductionServiceInterface
     */
    private $sourceDeductionService;

    /**
     * @var SourceDeductionRequestsFromSourceSelectionFactory
     */
    private $sourceDeductionRequestsFromSourceSelectionFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemToSellFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @var SourcesRepository
     */
    private $sourceRepository;

    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * @var SourcesConverter
     */
    private $sourcesConverter;

    /**
     * @var SourcesFactory
     */
    protected $sourcesFactory;

    /**
     * @param GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @param SourcesRepository $sourceRepository
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param SourcesConverter $sourcesConverter
     * @param SourcesFactory $sourcesFactory
     */
    public function __construct(
        GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        SourcesRepository $sourceRepository,
        OrderExtensionFactory $orderExtensionFactory,
        SourcesConverter $sourcesConverter,
        SourcesFactory $sourcesFactory
    ) {
        $this->getSourceSelectionResultFromOrder = $getSourceSelectionResultFromOrder;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestsFromSourceSelectionFactory = $sourceDeductionRequestsFromSourceSelectionFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->sourceRepository = $sourceRepository;
        $this->orderExtensionFactory = $orderExtensionFactory;
        $this->sourcesConverter = $sourcesConverter;
        $this->sourcesFactory = $sourcesFactory;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order->getOrigData('entity_id')) {
            return;
        }

        $sourceSelectionResult = $this->getSourceSelectionResultFromOrder->execute($order);

        $sourceModel = $this->sourcesFactory->create();
        $sourceModel->setOrderId($order->getEntityId());
        $sourceModel->setSources(
            $this->sourcesConverter->convertSourceSelectionItemsToSourcesArray($sourceSelectionResult->getSourceSelectionItems())
        );
        $this->sourceRepository->save($sourceModel);

        /** @var SalesEventInterface $salesEvent */
        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_ORDER_PLACED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => $order->getEntityId(),
        ]);

        $sourceDeductionRequests = $this->sourceDeductionRequestsFromSourceSelectionFactory->create(
            $sourceSelectionResult,
            $salesEvent,
            (int)$order->getStore()->getWebsiteId()
        );

        foreach ($sourceDeductionRequests as $sourceDeductionRequest) {
            $this->sourceDeductionService->execute($sourceDeductionRequest);
            $this->placeCompensatingReservation($sourceDeductionRequest);
        }
    }

    /**
     * Place compensating reservation after source deduction
     *
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     */
    private function placeCompensatingReservation(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $items = [];
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $items[] = $this->itemToSellFactory->create([
                'sku' => $item->getSku(),
                'qty' => $item->getQty()
            ]);
        }
        $this->placeReservationsForSalesEvent->execute(
            $items,
            $sourceDeductionRequest->getSalesChannel(),
            $sourceDeductionRequest->getSalesEvent()
        );
    }
}
