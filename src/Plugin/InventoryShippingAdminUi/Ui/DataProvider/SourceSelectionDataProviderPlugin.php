<?php

namespace Ampersand\DisableStockReservation\Plugin\InventoryShippingAdminUi\Ui\DataProvider;

use Magento\InventoryShippingAdminUi\Ui\DataProvider\SourceSelectionDataProvider;

class SourceSelectionDataProviderPlugin
{
    public function afterGetData(SourceSelectionDataProvider $subject, $result)
    {
        foreach ($result as &$data) {
            foreach ($data['items'] as &$item) {
                $item['isManageStock'] = 0;
            }
        }

        return $result;
    }
}