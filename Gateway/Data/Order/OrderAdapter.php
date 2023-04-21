<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Data\Order;

use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\Order\AddressAdapterFactory;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;

/**
 * OrderAdapter created to have access to order object
 */
class OrderAdapter implements OrderAdapterInterface
{
    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var AddressAdapterFactory
     */
    private AddressAdapterFactory $addressAdapterFactory;

    /**
     * @param Order $order
     * @param AddressAdapterFactory $addressAdapterFactory
     */
    public function __construct(
        Order $order,
        AddressAdapterFactory $addressAdapterFactory
    ) {
        $this->order = $order;
        $this->addressAdapterFactory = $addressAdapterFactory;
    }

    /**
     * Returns currency code
     *
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->order->getBaseCurrencyCode();
    }

    /**
     * Returns order increment id
     *
     * @return string
     */
    public function getOrderIncrementId(): string
    {
        return $this->order->getIncrementId();
    }

    /**
     * Returns customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->order->getCustomerId();
    }

    /**
     * Returns billing address
     *
     * @return AddressAdapterInterface|null
     */
    public function getBillingAddress(): ?AddressAdapterInterface
    {
        if ($this->order->getBillingAddress()) {
            return $this->addressAdapterFactory->create(
                ['address' => $this->order->getBillingAddress()]
            );
        }

        return null;
    }

    /**
     * Returns shipping address
     *
     * @return AddressAdapterInterface|null
     */
    public function getShippingAddress(): ?AddressAdapterInterface
    {
        if ($this->order->getShippingAddress()) {
            return $this->addressAdapterFactory->create(
                ['address' => $this->order->getShippingAddress()]
            );
        }

        return null;
    }

    /**
     * Returns order store id
     *
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        return $this->order->getStoreId();
    }

    /**
     * Returns order id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->order->getEntityId();
    }

    /**
     * Returns order grand total amount
     *
     * @return float
     */
    public function getGrandTotalAmount(): float
    {
        return $this->order->getBaseGrandTotal();
    }

    /**
     * Returns list of line items in the cart
     *
     * @return OrderItemInterface[]
     */
    public function getItems(): array
    {
        return $this->order->getItems();
    }

    /**
     * Gets the remote IP address for the order.
     *
     * @return string|null Remote IP address.
     */
    public function getRemoteIp(): ?string
    {
        return $this->order->getRemoteIp();
    }

    /**
     * Get entire order object
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }
}
