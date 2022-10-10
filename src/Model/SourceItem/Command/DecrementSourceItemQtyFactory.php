<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Model\SourceItem\Command;

use Magento\Framework\ObjectManagerInterface;
use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty as DecrementSourceItemQty24;

class DecrementSourceItemQtyFactory
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

    /***
     * Wrap this up so that we can inject this class into the construtor of our PatchedSourceDeductionService
     *
     * That way we can get di compilation working across 2.3 and 2.4
     *
     * @return DecrementSourceItemQty24
     */
    public function create()
    {
        return $this->objectManager->create(DecrementSourceItemQty24::class);
    }
}
