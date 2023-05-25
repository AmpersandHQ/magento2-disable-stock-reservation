<?php
declare(strict_types=1);
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Config\Storage\WriterInterface;

$objectManager = Bootstrap::getObjectManager();
$configWriter = $objectManager->get(WriterInterface::class);
$configWriter->delete('cataloginventory/indexer/strategy');