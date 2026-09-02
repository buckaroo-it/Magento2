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

use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Magento\Sales\Model\Order;

/**
 * Emits the billing-country-based culture code so the Buckaroo SDK client sends
 * the correct "Culture" header for the transaction (see BuckarooAdapter).
 *
 * The value is keyed under {@see self::CULTURE_KEY}, which the adapter consumes
 * and strips before the request body is sent.
 */
class CultureDataBuilder extends AbstractDataBuilder
{
    /**
     * Build-subject key carrying the resolved culture code to the adapter.
     */
    public const CULTURE_KEY = 'buckaroo_culture';

    /**
     * @var CultureCodeResolver
     */
    private $cultureCodeResolver;

    /**
     * @param CultureCodeResolver $cultureCodeResolver
     */
    public function __construct(CultureCodeResolver $cultureCodeResolver)
    {
        $this->cultureCodeResolver = $cultureCodeResolver;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $order = $this->getOrder();

        $countryId = null;
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress !== null) {
            $countryId = $billingAddress->getCountryId();
        }

        $culture = $this->cultureCodeResolver->resolveForHeader($countryId, $this->getStoreLocale($order));

        if ($culture === null) {
            return [];
        }

        return [self::CULTURE_KEY => $culture];
    }

    /**
     * Get the locale of the store view the order was placed on.
     *
     * Used only to disambiguate multi-language countries; it is deterministic
     * across reserve and capture.
     *
     * @param Order $order
     *
     * @return string|null
     */
    private function getStoreLocale(Order $order): ?string
    {
        try {
            $locale = (string)$order->getStore()->getConfig('general/locale/code');

            return $locale !== '' ? $locale : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
