<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Ampersand\DisableStockReservation\Model\SourcesFactory;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources;
use Ampersand\DisableStockReservation\Model\Sources as SourceModel;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionResultInterface;
use Magento\InventorySourceSelectionApi\Api\Data\SourceSelectionItemInterfaceFactory;

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
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var SourceSelectionResultInterfaceFactory
     */
    private $sourceSelectionResultFactory;

    /**
     * @var SourceSelectionItemInterfaceFactory
     */
    private $sourceSelectionItemInterface;

    /**
     * SourcesRepository constructor.
     * @param SourcesFactory $sourcesFactory
     * @param Sources $sourcesResourceModel
     * @param SerializerInterface $serializer
     * @param SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory
     * @param SourceSelectionItemInterfaceFactory $sourceSelectionItemInterface
     */
    public function __construct(
        SourcesFactory $sourcesFactory,
        Sources $sourcesResourceModel,
        SerializerInterface $serializer,
        \Magento\Framework\Api\DataObjectHelper $dataObjectHelper,
        SourceSelectionResultInterfaceFactory $sourceSelectionResultFactory,
        SourceSelectionItemInterfaceFactory $sourceSelectionItemInterface
    ) {
        $this->sourcesFactory = $sourcesFactory;
        $this->sourcesResourceModel = $sourcesResourceModel;
        $this->serializer = $serializer;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->sourceSelectionResultFactory = $sourceSelectionResultFactory;
        $this->sourceSelectionItemInterface = $sourceSelectionItemInterface;

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

        $this->sourcesResourceModel->load(
            $sourcesModel,
            $orderId,
            'order_id'
        );

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
        $sourcesModel = $this->sourcesFactory->create();

        $this->sourcesResourceModel->load(
            $sourcesModel,
            $orderId,
            'order_id'
        );

        $sourcesArray = $this->serializer->unserialize($sourcesModel->getSources());
        $sourceSelectionItems = [];

        foreach ($sourcesArray as $item)
        {
            $sourceSelectionItem = $this->sourceSelectionItemInterface->create(
                [
                    'sourceCode' => $item['source_code'],
                    'sku' => $item['SKU'],
                    'qtyToDeduct' => $item['qty_to_deduct'],
                    'qtyAvailable' => $item['qty_available']
                ]
            );

        $sourceSelectionItems[] = $sourceSelectionItem;
        }

        $sourceSelectionResult = $this->sourceSelectionResultFactory->create(
            [
                'sourceItemSelections' => $sourceSelectionItems,
                'isShippable' => true
            ]
        );

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
        $sources = [];
        foreach ($sourcesItems as $item) {
            $sources[] = [
                'source_code' => $item->getSourceCode(),
                'SKU' => $item->getSku(),
                'qty_to_deduct' => $item->getQtyToDeduct(),
                'qty_available' => $item->getQtyAvailable()
            ];
        }

        $model = $this->getByOrderId($orderId);
        if (!$model->getId()) {
            $model->addData(
                [
                    'order_id' => $orderId,
                    'sources' => $this->serializer->serialize($sources)
                ]
            );
        } else {
            $model->setData('sources', $this->serializer = $this->serializer->serialize($sources));
        }

        try {
            $this->sourcesResourceModel->save($model);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $model;
    }
}
