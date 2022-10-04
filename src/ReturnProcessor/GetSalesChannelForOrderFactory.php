<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\ReturnProcessor;

use Magento\Framework\ObjectManagerInterface;
use Magento\InventorySales\Model\ReturnProcessor\GetSalesChannelForOrder as GetSalesChannelForOrder24;

class GetSalesChannelForOrderFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * constructor.
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
     * @return GetSalesChannelForOrder|GetSalesChannelForOrder24|mixed
     */
    public function create()
    {
        if (\class_exists(GetSalesChannelForOrder24::class)) {
            return $this->objectManager->create(GetSalesChannelForOrder24::class);
        }
        return $this->objectManager->create(
            GetSalesChannelForOrder::class
        );
    }
}
