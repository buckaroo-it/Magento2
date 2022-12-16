<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class PhoneDataBuilder implements BuilderInterface
{
    private string $addressType;

    /**
     * @param string $addressType
     */
    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $address = $this->getAddress($order);

        $telephone = $paymentDO->getPayment()->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $address->getTelephone() : $telephone);

        return ['phone' => [
            'mobile' => $telephone,
            'landline' => $telephone
        ]];
    }

    /**
     * Get Billing/Shipping Address
     *
     * @param Order $order
     * @return OrderAddressInterface|null
     */
    private function getAddress($order)
    {
        if ($this->addressType == 'shipping') {
            return $order->getShippingAddress();
        } else {
            return $order->getBillingAddress();
        }
    }
}
