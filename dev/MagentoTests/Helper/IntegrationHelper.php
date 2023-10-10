<?php
namespace Ampersand\DisableStockReservation\Test\Helper;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemDataCache;
use Magento\InventorySales\Model\GetStockBySalesChannelCache;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\InventoryIndexer\Model\GetStockItemData\CacheStorage;
use Magento\InventoryConfiguration\Plugin\InventoryConfiguration\Model\GetLegacyStockItemCache;
use Magento\InventoryConfiguration\Model\LegacyStockItem\CacheStorage as LegacyStockItemCache;

class IntegrationHelper
{
    public static function clearCaches()
    {
        self::clearSalesChannelCache();
        self::clearGetStockItemDataCache();
        self::clearGetLegacyStockItemCache();
        self::clearStockRegistryStorage();
    }

    public static function clearStockRegistryStorage()
    {
        if (class_exists(StockRegistryStorage::class)) {
            $storage = Bootstrap::getObjectManager()->get(StockRegistryStorage::class);
            $storage->clean();
        }
    }

    public static function clearSalesChannelCache()
    {
        if (class_exists(GetStockBySalesChannelCache::class)) {
            $getStockBySalesChannelCache = Bootstrap::getObjectManager()->get(GetStockBySalesChannelCache::class);
            $ref = new \ReflectionObject($getStockBySalesChannelCache);
            try {
                $refProperty = $ref->getProperty('channelCodes');
            } catch (\ReflectionException $exception) {
                $refProperty = $ref->getParentClass()->getProperty('channelCodes');
            }
            $refProperty->setAccessible(true);
            $refProperty->setValue($getStockBySalesChannelCache, []);
        }
    }

    public static function clearGetStockItemDataCache()
    {
        if (class_exists(GetStockItemDataCache::class) && class_exists(CacheStorage::class)) {
            $cacheStorage = Bootstrap::getObjectManager()->get(CacheStorage::class);
            if (method_exists($cacheStorage, '_resetState')) {
                $cacheStorage->_resetState();
            } else {
                $ref = new \ReflectionObject($cacheStorage);
                try {
                    $refProperty = $ref->getProperty('cachedItemData');
                } catch (\ReflectionException $exception) {
                    $refProperty = $ref->getParentClass()->getProperty('cachedItemData');
                }
                $refProperty->setAccessible(true);
                $refProperty->setValue($cacheStorage, [[]]);
            }
        } elseif (class_exists(GetStockItemDataCache::class)) {
            $getStockItemDataCache = Bootstrap::getObjectManager()->get(GetStockItemDataCache::class);
            $ref = new \ReflectionObject($getStockItemDataCache);
            try {
                $refProperty = $ref->getProperty('stockItemData');
            } catch (\ReflectionException $exception) {
                $refProperty = $ref->getParentClass()->getProperty('stockItemData');
            }
            $refProperty->setAccessible(true);
            $refProperty->setValue($getStockItemDataCache, []);
        }
    }

    public static function clearGetLegacyStockItemCache()
    {
        if (class_exists(GetLegacyStockItemCache::class)) {
            $object = Bootstrap::getObjectManager()->get(GetLegacyStockItemCache::class);
            $ref = new \ReflectionObject($object);
            try {
                $refProperty = $ref->getProperty('legacyStockItemsBySku');
            } catch (\ReflectionException $exception) {
                if (!$ref->getParentClass()) {
                    return;
                }
                $refProperty = $ref->getParentClass()->getProperty('legacyStockItemsBySku');
            }
            $refProperty->setAccessible(true);
            $refProperty->setValue($object, []);
        }
        if (class_exists(LegacyStockItemCache::class)) {
            $object = Bootstrap::getObjectManager()->get(LegacyStockItemCache::class);
            $ref = new \ReflectionObject($object);
            try {
                $refProperty = $ref->getProperty('cachedItems');
            } catch (\ReflectionException $exception) {
                if (!$ref->getParentClass()) {
                    return;
                }
                $refProperty = $ref->getParentClass()->getProperty('cachedItems');
            }
            $refProperty->setAccessible(true);
            $refProperty->setValue($object, []);
        }
    }
}
