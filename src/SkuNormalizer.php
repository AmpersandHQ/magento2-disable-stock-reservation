<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation;

class SkuNormalizer
{
    /**
     * @link https://github.com/magento/inventory/commit/89b5f02ef65479d9af22f90f86c98e163f476780
     * @param $sku
     * @return string
     */
    public static function normalize($sku)
    {
        return \mb_convert_case($sku, MB_CASE_LOWER, 'UTF-8');
    }
}
