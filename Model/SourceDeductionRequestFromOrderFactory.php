<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Model;

use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\Sales\Model\Order;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterfaceFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;

class SourceDeductionRequestFromOrderFactory
{
    /**
     * @var SourceDeductionRequestInterfaceFactory
     */
    private $sourceDeductionRequestFactory;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @param SourceDeductionRequestInterfaceFactory $sourceDeductionRequestFactory
     * @param SalesChannelInterfaceFactory $salesChannelFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param WebsiteRepositoryInterface $websiteRepository
     */
    public function __construct(
        SourceDeductionRequestInterfaceFactory $sourceDeductionRequestFactory,
        SalesChannelInterfaceFactory $salesChannelFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->sourceDeductionRequestFactory = $sourceDeductionRequestFactory;
        $this->salesChannelFactory = $salesChannelFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * @param Order $order
     * @param string $sourceCode
     * @param array $items
     * @return SourceDeductionRequestInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(
        Order $order,
        string $sourceCode,
        array $items
    ): SourceDeductionRequestInterface {
        $websiteId = $order->getStore()->getWebsiteId();

        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_SHIPMENT_CREATED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => $order->getEntityId()
        ]);

        $websiteCode = $this->websiteRepository->getById($websiteId)->getCode();
        $salesChannel = $this->salesChannelFactory->create([
            'data' => [
                'type' => SalesChannelInterface::TYPE_WEBSITE,
                'code' => $websiteCode
            ]
        ]);

        return $this->sourceDeductionRequestFactory->create([
            'sourceCode' => $sourceCode,
            'items' => $items,
            'salesChannel' => $salesChannel,
            'salesEvent' => $salesEvent
        ]);
    }
}
