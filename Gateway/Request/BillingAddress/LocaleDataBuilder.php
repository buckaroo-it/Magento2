<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BillingAddress;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class LocaleDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
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
     * @return string
     */
    private function getLocaleCode($order): string
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
