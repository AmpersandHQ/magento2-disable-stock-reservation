<?php

namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Ampersand\DisableStockReservation\Model\ResourceModel\Sources;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterface;
use Ampersand\DisableStockReservation\Api\SourcesRepositoryInterface;
use Ampersand\DisableStockReservation\Api\Data\SourcesInterfaceFactory;
use Ampersand\DisableStockReservation\Model\Sources as SourcesModel;

/**
 * Class SourcesRepository
 * @package Ampersand\DisableStockReservation\Model
 */
class SourcesRepository implements SourcesRepositoryInterface
{
    /**
     * @var SourcesInterfaceFactory
     */
    protected $sourcesFactory;

    /**
     * @var Sources
     */
    protected $sourcesResourceModel;

    /**
     * SourcesRepository constructor.
     * @param SourcesInterfaceFactory $sourcesFactory
     * @param Sources $sourcesResourceModel
     */
    public function __construct(
        SourcesInterfaceFactory $sourcesFactory,
        Sources $sourcesResourceModel
    ) {
        $this->sourcesFactory = $sourcesFactory;
        $this->sourcesResourceModel = $sourcesResourceModel;
    }

    /**
     * @param string $orderId
     *
     * @return SourcesInterface
     * @throws \Exception
     */
    public function getByOrderId(string $orderId): SourcesInterface
    {
        /** @var SourcesModel $sourcesModel */
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

    /**
     * @param SourcesInterface $model
     *
     * @return SourcesInterface
     * @throws CouldNotSaveException
     */
    public function save(SourcesInterface $model): SourcesInterface
    {
        try {
            // We're checking the model here because the Sources model extending the AbstractModel and implementing the
            // interface and the save method needs the AbstractModel as parameter to save in the DB
            if (!$model instanceof SourcesModel) {
                throw new LocalizedException(__('expects Magento\Framework\Model\AbstractModel'));
            }
            $this->sourcesResourceModel->save($model);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $model;
    }
}
