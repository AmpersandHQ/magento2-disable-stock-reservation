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
use Ampersand\DisableStockReservation\Model\SourcesFactory;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources;
use Magento\Framework\Serialize\SerializerInterface;

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
     * @var SourcesFactory
     */
    protected $sourcesFactory;

    /**
     * @var Sources
     */
    protected $sourcesResourceModel;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     * @param SourcesFactory $sourcesFactory
     * @param Sources $sourcesResourceModel
     * @param SerializerInterface $serializer
     */
    public function __construct(
        GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent,
        SourcesFactory $sourcesFactory,
        Sources $sourcesResourceModel,
        SerializerInterface $serializer
    ) {
        $this->getSourceSelectionResultFromOrder = $getSourceSelectionResultFromOrder;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestsFromSourceSelectionFactory = $sourceDeductionRequestsFromSourceSelectionFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
        $this->sourcesFactory = $sourcesFactory;
        $this->sourcesResourceModel = $sourcesResourceModel;
        $this->serializer = $serializer;
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

        if (!$extensionAttributes = $order->getExtensionAttributes()) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        $extensionAttributes->setSources(
            $sourcesItems = $sourceSelectionResult->getSourceSelectionItems()
        );

        $order->setExtensionAttributes($extensionAttributes);

        $sources = [];
        foreach ($sourcesItems as $item) {
            $sources[] = [
                'source_code' => $item->getSourceCode(),
                'SKU' => $item->getSku(),
                'qty_to_deduct' => $item->getQtyToDeduct(),
                'qty_available' => $item->getQtyAvailable()
            ];
        }

        $model = $this->sourcesFactory->create();
        $this->sourcesResourceModel->load($model, $orderId = $order->getId(), 'order_id');

        if (!$model->getId()) {
            $model->addData(
                [
                    'order_id' => $orderId,
                    'sources' => $this->serializer->serialize($sources)
                ]
            );
        } else {
            $model->setData('sources', $this->serializer->serialize($sources));
        }

        $this->sourcesResourceModel->save($model);

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