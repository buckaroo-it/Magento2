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

namespace Buckaroo\Magento2\Gateway\Request;

class LocaleDataBuilder extends AbstractDataBuilder
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['locale' => $this->getLocaleCode()];
    }

    /**
     * Retrieves the locale code based on the billing address country.
     *
     * @return string
     */
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
