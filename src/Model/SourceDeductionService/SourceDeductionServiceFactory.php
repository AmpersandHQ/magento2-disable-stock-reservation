<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Model\SourceDeductionService;

use Magento\Framework\ObjectManagerInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionService;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty as DecrementSourceItemQty243AndAbove;

class SourceDeductionServiceFactory
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
     * For magento 2.4.3 and above return the workaround copy
     * For magento 2.4.2 and below use the vanilla implementation
     *
     * @return SourceDeductionService|PatchedSourceDeductionService|mixed
     */
    public function create()
    {
        if (\class_exists(DecrementSourceItemQty243AndAbove::class)) {
            return $this->objectManager->create(PatchedSourceDeductionService::class);
        }
        return $this->objectManager->create(
            SourceDeductionService::class
        );
    }
}
