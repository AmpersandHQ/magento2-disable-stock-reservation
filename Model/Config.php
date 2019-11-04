<?php
namespace Ampersand\DisableStockReservation\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    /** @var ScopeConfigInterface  */
    protected $scopeConfig;

    /**
     * SystemConfig constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isStockReservationDisabled() : bool
    {
        $stockReservationDisabled = $this->scopeConfig->getValue(
            "ampersand_disable_stock_reservation/general/disable_stock_reservation",
            ScopeInterface::SCOPE_STORE
        );

        return $stockReservationDisabled === '1' ? true : false;
    }
}
