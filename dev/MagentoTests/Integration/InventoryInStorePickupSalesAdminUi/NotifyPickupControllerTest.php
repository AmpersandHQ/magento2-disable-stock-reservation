<?php
declare(strict_types=1);
namespace Ampersand\DisableStockReservation\Test\Integration\InventoryInStorePickupSalesAdminUi;

/**
 * @magentoAppArea adminhtml
 */
class NotifyPickupControllerTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    public function testNotifyProducts()
    {
        if (!class_exists(\Magento\InventoryInStorePickupSales\Model\Order\IsFulfillable::class)) {
            $this->markTestSkipped('Test not required on older magento versions');
            return;
        }
        // TODO use tddwizard/magento2-fixtures to generate a product, an order, and then run this controller against it and assert against the results
        $this->assertTrue(true);
    }
}
