<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Ampersand\DisableStockReservation\Model\SourcesFactory;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources;
use Ampersand\DisableStockReservation\Model\Sources as SourceModel;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Ampersand\DisableStockReservation\Service\SourcesConverter;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources\CollectionFactory;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;

/**
 * Class SourcesRepository
 * @package Ampersand\DisableStockReservation\Model
 */
class SourcesRepository
{
    /**
     * @var SourcesFactory
     */
    protected $sourcesFactory;

    /**
     * @var Sources
     */
    protected $sourcesResourceModel;

    /**
     * @var SourceSelectionResultInterfaceFactory
     */
    private $sourceSelectionResultFactory;

    /**
     * @var SourcesConverter
     */
    private $sourcesConverter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * SourcesRepository constructor.
     * @param SourcesFactory $sourcesFactory
     * @param Sources $sourcesResourceModel
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory
     * @param SourcesConverter $sourcesConverter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        SourcesFactory $sourcesFactory,
        Sources $sourcesResourceModel,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory,
        SourcesConverter $sourcesConverter,
        CollectionFactory $collectionFactory
    ) {
        $this->sourcesFactory = $sourcesFactory;
        $this->sourcesResourceModel = $sourcesResourceModel;
        $this->sourceSelectionResultFactory = $sourceSelectionResultFactory;
        $this->sourcesConverter = $sourcesConverter;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param string $orderId
     *
     * @return SourceModel
     * @throws \Exception
     */
    public function getByOrderId(string $orderId): SourceModel
    {
        /** @var SourceModel $sourcesModel */
        $sourcesModel = $this->sourcesFactory->create();
        $this->sourcesResourceModel->load(
            $sourcesModel,
            $orderId,
            'order_id'
        );

        if (!$sourcesModel->getId()) {
            throw new NoSuchEntityException(__('Source model with the order ID "%1', $orderId));
        }

        return $sourcesModel;
    }

    public function getOrdersSourcesCollection(OrderSearchResultInterface $result)
    {
        $resultIds = [];
        foreach ($result as $resultItem) {
            $resultIds [] = $resultItem->getEntityId();
        }

        $ordersSourcesItems = $this->collectionFactory->create()
            ->addFieldToFilter('order_id', ['in' => $resultIds])
            ->getItems();

        $orderSources = [];
        foreach ($ordersSourcesItems as $item) {
            $orderSources[$item->getOrderId()] = $this->sourcesConverter->
            convertSourcesArrayToSourceSelectionItems($item->getSources());
        }

        return $orderSources;
    }

    /**
     * @param string $orderId
     *
     * @return SourceSelectionResultInterface|null
     */
    public function getSourceSelectionResultByOrderId(string $orderId): ?SourceSelectionResultInterface
    {
        try {
            /** @var SourceModel $sourcesModel */
            $sourcesModel = $this->getByOrderId($orderId);
        } catch (NoSuchEntityException $exception) {
            return null;
        }

        $sourceSelectionItems = $this->sourcesConverter
            ->convertSourcesArrayToSourceSelectionItems($sourcesModel->getSources());

        $sourceSelectionResult = $this->sourceSelectionResultFactory->create([
            'sourceItemSelections' => $sourceSelectionItems,
            'isShippable' => true
        ]);

        return $sourceSelectionResult;
    }

    /**
     * @param SourcesInterface $model
     *
     * @return SourcesInterface
     * @throws CouldNotSaveException
     */
    public function save(SourcesInterface $model): SourcesInterface
    {
        try {
            $this->sourcesResourceModel->save($model);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $model;
    }
}
