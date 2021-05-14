<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

abstract class TruncateReservationsAbstract extends Command
{
    /**
     * Constructor
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->_resource = $resource;
        parent::__construct();
    }

    public function truncateTable($tableName)
    {
        try {
            if (empty($tableName)) {
                return 'need table';
            }

            $tableName = $this->_resource->getTableName($tableName);
            $sql = "TRUNCATE TABLE {$tableName}";
            $this->_resource->getConnection()->query($sql);
            return 'Success';

        } catch (Exception $e) {
            return 'Caught exception: '. $e->getMessage();
        }

    }
}
