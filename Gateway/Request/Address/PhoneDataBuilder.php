<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

class PhoneDataBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    private $addressType;

    /**
     * @param string $addressType
     */
    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    /**
     * @inheritdoc
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
        $telephone = $telephone ?: ($address ? $address->getTelephone() : '');

        // Only build phone data if telephone is not empty
        if (empty($telephone)) {
            return [];
        }

        return $this->returnPhoneDetails($telephone, $telephone);
    }

    /**
     * Get Billing/Shipping Address
     *
     * @param Order $order
     *
     * @return OrderAddressInterface|null
     */
    private function getAddress(Order $order): ?OrderAddressInterface
    {
        return ($this->addressType == 'shipping')
            ? $order->getShippingAddress()
            : $order->getBillingAddress();
    }

    /**
     * Return Phone Details
     *
     * @param string $telephone
     * @param string $landline
     *
     * @return array[]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function returnPhoneDetails(string $telephone, string $landline = ''): array
    {
        return [
            'phone' => [
                'mobile' => $telephone
            ]
        ];
    }
}
