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

namespace Buckaroo\Magento2\Gateway\Request\BillingAddress;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class LocaleDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        return ['locale' => $this->getLocaleCode($order)];
    }

    /**
     * Get Locale Code By Country ID from Billing Address
     *
     * @param Order $order
     *
     * @return string
     */
    private function getLocaleCode(Order $order): string
    {
        $country = $order->getBillingAddress()->getCountryId();

        if ($country == 'CN') {
            $localeCode = 'zh-CN';
        } elseif ($country == 'TW') {
            $localeCode = 'zh-TW';
        } else {
            $localeCode = 'en-US';
        }
        return $localeCode;
    }
}
