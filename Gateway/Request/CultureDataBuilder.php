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
