<?php
declare(strict_types=1);

namespace Ampersand\DisableStockReservation\Model;

use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestExtensionInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\AddressInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\AddressInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class GetInventoryRequestFromOrder
{
    /**
     * @var InventoryRequestInterfaceFactory
     */
    private $inventoryRequestFactory;

    /**
     * @var InventoryRequestExtensionInterfaceFactory
     */
    private $inventoryRequestExtensionFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressInterfaceFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @param InventoryRequestInterfaceFactory $inventoryRequestFactory
     * @param InventoryRequestExtensionInterfaceFactory $inventoryRequestExtensionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     */
    public function __construct(
        InventoryRequestInterfaceFactory $inventoryRequestFactory,
        InventoryRequestExtensionInterfaceFactory $inventoryRequestExtensionFactory,
        OrderRepositoryInterface $orderRepository,
        AddressInterfaceFactory $addressInterfaceFactory,
        StoreManagerInterface $storeManager,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
    ) {
        $this->inventoryRequestFactory = $inventoryRequestFactory;
        $this->inventoryRequestExtensionFactory = $inventoryRequestExtensionFactory;
        $this->orderRepository = $orderRepository;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->storeManager = $storeManager;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
    }

    /**
     * Same as GetInventoryRequestFromOrder, but takes an order instead of an order id
     * because in this scenario the order has not been saved yet.
     * 
     * @param OrderInterface $order
     * @param array $requestItems
     * @return InventoryRequestInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(OrderInterface $order, array $requestItems): InventoryRequestInterface
    {
        $store = $this->storeManager->getStore($order->getStoreId());
        $stock = $this->stockByWebsiteIdResolver->execute((int)$store->getWebsiteId());

        $inventoryRequest = $this->inventoryRequestFactory->create([
            'stockId' => $stock->getStockId(),
            'items'   => $requestItems
        ]);

        $address = $this->getAddressFromOrder($order);
        if ($address !== null) {
            $extensionAttributes = $this->inventoryRequestExtensionFactory->create();
            $extensionAttributes->setDestinationAddress($address);
            $inventoryRequest->setExtensionAttributes($extensionAttributes);
        }

        return $inventoryRequest;
    }

    /**
     * Create an address from an order
     *
     * @param OrderInterface $order
     * @return null|AddressInterface
     */
    private function getAddressFromOrder(OrderInterface $order): ?AddressInterface
    {
        /** @var Address $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress === null) {
            return null;
        }

        return $this->addressInterfaceFactory->create([
            'country' => $shippingAddress->getCountryId(),
            'postcode' => $shippingAddress->getPostcode(),
            'street' => implode("\n", $shippingAddress->getStreet()),
            'region' => $shippingAddress->getRegion() ?? $shippingAddress->getRegionCode() ?? '',
            'city' => $shippingAddress->getCity()
        ]);
    }
}
