# AmpersandHQ/magento2-disable-stock-reservation

[![Build Status](https://app.travis-ci.com/AmpersandHQ/magento2-disable-stock-reservation.svg?branch=master)](https://app.travis-ci.com/AmpersandHQ/magento2-disable-stock-reservation)

This module disables the inventory reservation logic introduced as part of MSI in Magento 2.3.3 - see
https://github.com/magento/inventory/issues/2269 for more information about the way MSI was implemented, and the issues
that can happen with external WMS integrations.

## The Problem

During the order placement and fulfilment processes, Magento's MSI implementation will not decrement stock on order
placement - it will only do so on order shipment and refund.

## Our Approach

This module will:

* Prevent all writes to the `inventory_reservation` table. It does so by using an `around` plugin on `PlaceReservationsForSalesEventInterface`
* Trigger stock deductions on order placement. See `inventory_sales_source_deduction_processor` plugin on `Magento\Sales\Model\Service\OrderService`.
* Prevent stock deductions on order shipment. See disabled `inventory_sales_source_deduction_processor` observer on `sales_order_shipment_save_after` event.
* Replenish stock for cancelled order items. See `inventory` observer on `sales_order_item_cancel` event.
* Replenish stock when a credit memo is issued. See `src/Observer/RestoreSourceItemQuantityOnRefundObserver.php`
  * Requires that "Back to stock" is checked or "Automatically Return Credit Memo Item to Stock" is configured
    * https://docs.magento.com/user-guide/configuration/catalog/inventory.html#product-stock-options
* Truncate (ie, remove all historic entries from) the `inventory_reservation` table upon installation.

## Additional Notes

* Both the `inventory` and `cataloginventory_stock` should be on the same mode (`Update on Save` or `Schedule`) for this module to work as expected. If you are running this on `Schedule` you should have crons activated.
