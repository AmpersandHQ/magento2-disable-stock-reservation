<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Model\SourceItem\Command;

use Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
// AMPERSAND CHANGE START
//use Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;
use Ampersand\DisableStockReservation\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;
// AMPERSAND CHANGE END
use Magento\Inventory\Model\SourceItem\Validator\SourceItemsValidator;
use Psr\Log\LoggerInterface;

/**
 * Decrement quantity for source item
 *
 * This file is identical to the core except with DecrementQtyForMultipleSourceItem replaced with our version, it does
 * not use an interface so swapping it out is a bit more difficult
 *
 * We could make our version extend the core offering, but that makes supporting this version of this module across
 * multiple magento versions more difficult
 */
class DecrementSourceItemQty
{
    /**
     * @var SourceItemsValidator
     */
    private $sourceItemsValidator;

    /**
     * @var DecrementQtyForMultipleSourceItem
     */
    private $decrementSourceItem;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SourceItemsValidator $sourceItemsValidator
     * @param DecrementQtyForMultipleSourceItem $decrementSourceItem
     * @param LoggerInterface $logger
     */
    public function __construct(
        SourceItemsValidator $sourceItemsValidator,
        DecrementQtyForMultipleSourceItem $decrementSourceItem,
        LoggerInterface $logger
    ) {
        $this->sourceItemsValidator = $sourceItemsValidator;
        $this->decrementSourceItem = $decrementSourceItem;
        $this->logger = $logger;
    }

    /**
     * Decrement quantity for Multiple Source
     *
     * @param array $sourceItemDecrementData
     * @return void
     * @throws InputException
     * @throws ValidationException
     * @throws CouldNotSaveException
     */
    public function execute(array $sourceItemDecrementData): void
    {
        $this->validateSourceItems($sourceItemDecrementData);
        try {
            $this->decrementSourceItem->execute($sourceItemDecrementData);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotSaveException(__('Could not save Source Item'), $e);
        }
    }

    /**
     * Validate source items data
     *
     * @param array $sourceItemDecrementData
     * @return void
     * @throws InputException
     * @throws ValidationException
     */
    private function validateSourceItems(array $sourceItemDecrementData): void
    {
        $sourceItems = array_column($sourceItemDecrementData, 'source_item');
        if (empty($sourceItems)) {
            throw new InputException(__('Input data is empty'));
        }
        $validationResult = $this->sourceItemsValidator->validate($sourceItems);
        if (!$validationResult->isValid()) {
            $error = current($validationResult->getErrors());
            throw new ValidationException(__('Validation Failed: ' . $error), null, 0, $validationResult);
        }
    }
}
