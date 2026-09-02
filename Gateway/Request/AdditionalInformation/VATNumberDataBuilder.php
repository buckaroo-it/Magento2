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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class VATNumberDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder()->getOrder();

        $vatNumber = $payment->getAdditionalInformation('customer_VATNumber') ?? '';

        if ($payment->getMethodInstance()->getCode() === 'buckaroo_magento2_billink') {
            $billingAddress = $order->getBillingAddress();
            $shippingAddress = $order->getShippingAddress();

            $billingCompany = $billingAddress ? trim($billingAddress->getCompany() ?? '') : '';
            $shippingCompany = $shippingAddress ? trim($shippingAddress->getCompany() ?? '') : '';
            $hasCompany = !empty($billingCompany) || !empty($shippingCompany);

            if (empty($vatNumber) && !$hasCompany) {
                return [];
            }
        }

        return ['vATNumber' => $vatNumber];
    }
}
