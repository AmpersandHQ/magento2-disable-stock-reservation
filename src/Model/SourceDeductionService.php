<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Model;

use Ampersand\DisableStockReservation\Model\SourceDeductionService\SourceDeductionServiceFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;

class SourceDeductionService implements SourceDeductionServiceInterface
{
    /**
     * @var SourceDeductionServiceFactory
     */
    private $sourceDeductionServiceFactory;

    /**
     * @param SourceDeductionServiceFactory $sourceDeductionServiceFactory
     */
    public function __construct(
        SourceDeductionServiceFactory $sourceDeductionServiceFactory
    ) {
        $this->sourceDeductionServiceFactory = $sourceDeductionServiceFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $this->sourceDeductionServiceFactory->create()->execute($sourceDeductionRequest);
    }
}
