<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Sales\Api\Data\OrderAddressInterface;

class LocaleDataBuilder extends AbstractDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['locale' => $this->getLocaleCode()];
    }

    private function getLocaleCode(): string
    {
        $country = $this->getOrder()->getBillingAddress()->getCountryId();

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
