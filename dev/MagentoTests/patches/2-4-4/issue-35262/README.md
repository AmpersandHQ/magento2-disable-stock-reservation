https://github.com/magento/magento2/issues/35262

```
Error in fixture: "\/var\/www\/html\/vendor\/magento\/module-inventory-api\/Test\/_files\/source_items.php".
 SQLSTATE[42S02]: Base table or view not found: 1146 Table 'magento_integration_tests.trv_reservations_temp_for_stock_10' doesn't exist, query was: SELECT `source_item`.`sku`, SUM(IF(source_item.status = 0, 0, source_item.quantity)) AS `quantity`, IF((reservations.reservation_qty IS NULL OR (SUM(source_item.quantity) + reservations.reservation_qty) > 0) AND (((legacy_stock_item.use_config_backorders = 0 AND legacy_stock_item.backorders <> 0) AND (legacy_stock_item.min_qty >= 0 OR legacy_stock_item.qty > legacy_stock_item.min_qty) AND SUM(IF(source_item.status = 0, 0, 1))) OR ((legacy_stock_item.use_config_manage_stock = 0 AND legacy_stock_item.manage_stock = 0)) OR ((legacy_stock_item.use_config_min_qty = 1 AND SUM(IF(source_item.status = 0, 0, source_item.quantity)) > 0) OR (legacy_stock_item.use_config_min_qty = 0 AND SUM(IF(source_item.status = 0, 0, source_item.quantity)) > legacy_stock_item.min_qty)) OR (product.sku IS NULL)), 1, 0) AS `is_salable` FROM `trv_inventory_source_item` AS `source_item`
 LEFT JOIN `trv_catalog_product_entity` AS `product` ON product.sku = source_item.sku
 LEFT JOIN `trv_cataloginventory_stock_item` AS `legacy_stock_item` ON product.entity_id = legacy_stock_item.product_id
 LEFT JOIN `trv_reservations_temp_for_stock_10` AS `reservations` ON  source_item.sku = reservations.sku WHERE (source_item.source_code IN ('eu-1', 'eu-2', 'eu-3')) AND (source_item.sku IN ('SKU-1', 'SKU-6', 'SKU-3', 'SKU-4')) GROUP BY `source_item`.`sku`
```

Fixed in https://github.com/magento/inventory/commit/d236405c22d3005c642c70348cfd372dbcb98b76