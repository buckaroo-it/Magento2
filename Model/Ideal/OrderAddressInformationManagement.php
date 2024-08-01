<?php

namespace Buckaroo\Magento2\Model\Ideal;

use Buckaroo\Magento2\Api\Data\Ideal\OrderAddressesInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class OrderAddressInformationManagement implements OrderAddressesInformationManagementInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Update the shipping and billing information for an order.
     *
     * @param int|string $orderId
     * @param OrderAddressInterface $shippingAddress
     * @param OrderAddressInterface $billingAddress
     * @return bool
     * @throws LocalizedException
     */
    public function updateAddressInformation($orderId, OrderAddressInterface $shippingAddress, OrderAddressInterface $billingAddress)
    {
        try {
            $this->logger->info('Updating addresses for order ID: ' . $orderId);

            $order = $this->orderRepository->get($orderId);

            if (!$order->getId()) {
                throw new NoSuchEntityException(__('Order does not exist.'));
            }

            // Update shipping address details
            $order->getShippingAddress()
                ->setFirstname($shippingAddress->getFirstname())
                ->setLastname($shippingAddress->getLastname())
                ->setCompany($shippingAddress->getCompany())
                ->setStreet($shippingAddress->getStreet())
                ->setCity($shippingAddress->getCity())
                ->setPostcode($shippingAddress->getPostcode())
                ->setCountryId($shippingAddress->getCountryId())
                ->setTelephone($shippingAddress->getTelephone());

            // Update billing address details
            $order->getBillingAddress()
                ->setFirstname($billingAddress->getFirstname())
                ->setLastname($billingAddress->getLastname())
                ->setCompany($billingAddress->getCompany())
                ->setStreet($billingAddress->getStreet())
                ->setCity($billingAddress->getCity())
                ->setPostcode($billingAddress->getPostcode())
                ->setCountryId($billingAddress->getCountryId())
                ->setTelephone($billingAddress->getTelephone());

            $this->orderRepository->save($order);

            $this->logger->info('Addresses updated successfully.');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating addresses: ' . $e->getMessage());
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}
