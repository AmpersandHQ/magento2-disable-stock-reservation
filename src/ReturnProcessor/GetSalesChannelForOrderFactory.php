<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\ReturnProcessor;

use Magento\Framework\ObjectManagerInterface;

class GetSalesChannelForOrderFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * ResponseFactory constructor.
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * For magento 2.4 return the core provided class
     * For magento 2.3 return the workaround copy of that class
     *
     * @return GetSalesChannelForOrder|\Magento\InventorySales\Model\ReturnProcessor\GetSalesChannelForOrder|mixed
     */
    public function create()
    {
        if (\class_exists(\Magento\InventorySales\Model\ReturnProcessor\GetSalesChannelForOrder::class)) {
            return $this->objectManager->create(
                \Magento\InventorySales\Model\ReturnProcessor\GetSalesChannelForOrder::class
            );
        }
        return $this->objectManager->create(
            GetSalesChannelForOrder::class
        );
    }
}