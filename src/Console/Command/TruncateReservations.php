<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TruncateReservations extends TruncateReservationsAbstract
{

    const NAME_ARGUMENT = "name";
    const NAME_OPTION = "option";

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $name = $input->getArgument(self::NAME_ARGUMENT);
        $option = $input->getOption(self::NAME_OPTION);

        $output->writeln('');
        $output->writeln('-----------------------------------------------------------------');
        $output->writeln('Need help?');
        $output->writeln('https://github.com/AmpersandHQ/magento2-disable-stock-reservation');
        $output->writeln('-----------------------------------------------------------------');
        $output->writeln('');

        switch ($name) {
            case 'inventory-reservation':
                $tableName = "inventory_reservation";
                break;
            default:
                $tableName = "";
        }
        $output->writeln("Status: " . $this->truncateTable($tableName));

    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("ampersand:disable-stock-reservation");
        $this->setDescription("Clears the inventory_reservation table if it has values");
        $this->setDefinition([
            new InputArgument(self::NAME_ARGUMENT, InputArgument::OPTIONAL, "Name"),
            new InputOption(self::NAME_OPTION, "-a", InputOption::VALUE_NONE, "Option functionality")
        ]);
        parent::configure();
    }
}

