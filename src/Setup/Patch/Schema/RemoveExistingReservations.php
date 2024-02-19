<?php

namespace Ampersand\DisableStockReservation\Setup\Patch\Schema;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class RemoveExistingReservations implements SchemaPatchInterface
{
    private const INVENTORY_RESERVATION_TABLE_NAME = 'inventory_reservation';
    private $setup;

    public function __construct(
        SchemaSetupInterface $setup
    ) {
        $this->setup = $setup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $connection = $this->setup->getConnection();
        $tableName = $connection->getTableName(self::INVENTORY_RESERVATION_TABLE_NAME);

        if ($connection->isTableExists($tableName)) {
            $connection->truncateTable($tableName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
