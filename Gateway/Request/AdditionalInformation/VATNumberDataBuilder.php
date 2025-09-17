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
