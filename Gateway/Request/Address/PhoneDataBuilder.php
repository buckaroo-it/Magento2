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
    private string $addressType;

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

        return $this->returnPhoneDetails($telephone, $telephone);
    }

    /**
     * Get Billing/Shipping Address
     *
     * @param Order $order
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
