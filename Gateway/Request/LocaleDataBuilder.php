<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

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

        $map = [
            'CN' => 'zh-CN',
            'TW' => 'zh-TW'
        ];

        return $map[$country] ?? 'en-US';
    }
}
