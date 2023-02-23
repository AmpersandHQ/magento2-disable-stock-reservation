<?php declare(strict_types=1);

/**
 * @author Mark Fischmann https://github.com/markfischmann
 */

namespace Ampersand\DisableStockReservation\Model\ResourceModel\SourceItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;

/**
 * Preference class to override Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem
 */
class DecrementQtyForMultipleSourceItem
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Decrement qty for source item.
     *
     * In addition to the quantity, we add the status that needs to be updated when
     * product is either going out of stock, or going back in stock.
     *
     * @param array $decrementItems
     * @return void
     */
    public function execute(array $decrementItems): void
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);
        if (!count($decrementItems)) {
            return;
        }
        foreach ($decrementItems as $decrementItem) {
            $sourceItem = $decrementItem['source_item'];
            $status = (int) $sourceItem->getStatus();
            $where = [
                'source_code = ?' => $sourceItem->getSourceCode(),
                'sku = ?' => $sourceItem->getSku()
            ];
            $connection->update(
                [$tableName],
                [
                    'quantity' => new \Zend_Db_Expr('quantity - ' . $decrementItem['qty_to_decrement']),
                    'status' => $status
                ],
                $where
            );
        }
    }
}
