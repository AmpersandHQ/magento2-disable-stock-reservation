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
     * SourcesRepository constructor.
     * @param SourcesFactory $sourcesFactory
     * @param Sources $sourcesResourceModel
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory
     * @param SourcesConverter $sourcesConverter
     */
    public function __construct(
        SourcesFactory $sourcesFactory,
        Sources $sourcesResourceModel,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory,
        SourcesConverter $sourcesConverter
    ) {
        $this->sourcesFactory = $sourcesFactory;
        $this->sourcesResourceModel = $sourcesResourceModel;
        $this->sourceSelectionResultFactory = $sourceSelectionResultFactory;
        $this->sourcesConverter = $sourcesConverter;
    }

    /**
     * @param string $orderId
     *
     * @return SourceModel
     * @throws NoSuchEntityException
     */
    public function getByOrderId(string $orderId): SourceModel
    {
        /** @var SourceModel $sourcesModel */
        $sourcesModel = $this->sourcesFactory->create();

        try {
            $this->sourcesResourceModel->load(
                $sourcesModel,
                $orderId,
                'order_id'
            );
        } catch (\Exception $exception) {
            throw new NoSuchEntityException(__($exception->getMessage()));
        }

        return $sourcesModel;
    }

    /**
     * @param string $orderId
     *
     * @return SourceSelectionResultInterface
     */
    public function getSourceSelectionResultByOrderId(string $orderId): SourceSelectionResultInterface
    {
        /** @var SourceModel $sourcesModel */
        $sourcesModel = $this->getByOrderId($orderId);
        $sourceSelectionItems = $this->sourcesConverter
            ->convertSourcesArrayToSourceSelectionItems($sourcesModel->getSources());

        $sourceSelectionResult = $this->sourceSelectionResultFactory->create([
            'sourceItemSelections' => $sourceSelectionItems,
            'isShippable' => true
        ]);

        return $sourceSelectionResult;
    }

    /**
     * @param array $sourcesItems
     * @param string $orderId
     *
     * @return SourceModel
     * @throws CouldNotSaveException
     */
    public function save(array $sourcesItems, string $orderId): SourceModel
    {
        $model = $this->getByOrderId($orderId);
        $model->setOrderId($orderId);
        $model->setSources(
            $this->sourcesConverter->convertSourceSelectionItemsToSourcesArray($sourcesItems)
        );

        try {
            $this->sourcesResourceModel->save($model);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $model;
    }
}
