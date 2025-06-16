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

namespace Buckaroo\Magento2\Model\Config\Source;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies as ConfigAllowedCurrencies;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Locale\TranslatedLists;
use Magento\Framework\Data\OptionSourceInterface;

class AllowedCurrencies implements OptionSourceInterface
{
    /**
     * @var ConfigAllowedCurrencies
     */
    protected ConfigAllowedCurrencies $allowedCurrenciesConfig;

    /**
     * @var Factory
     */
    protected Factory $configProviderMethodFactory;

    /**
     * @var TranslatedLists
     */
    protected TranslatedLists $listModels;

    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var CurrencyBundle
     */
    protected CurrencyBundle $currencyBundle;

    /**
     * @param ConfigAllowedCurrencies $allowedCurrenciesConfig
     * @param Factory $configProviderMethodFactory
     * @param CurrencyBundle $currencyBundle
     * @param ResolverInterface $localeResolver
     * @param TranslatedLists $listModels
     */
    public function __construct(
        ConfigAllowedCurrencies $allowedCurrenciesConfig,
        Factory $configProviderMethodFactory,
        CurrencyBundle $currencyBundle,
        ResolverInterface $localeResolver,
        TranslatedLists $listModels
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->allowedCurrenciesConfig = $allowedCurrenciesConfig;
        $this->currencyBundle = $currencyBundle;
        $this->localeResolver = $localeResolver;
        $this->listModels = $listModels;
    }

    /**
     * $method is what is defined in system.xml (i.e. ::ideal) and is directly passed to toOptionArray for method
     * configuration exemptions.
     *
     * @param string $method
     * @param array|null $params
     * @return array
     * @throws BuckarooException
     */
    public function __call(string $method, ?array $params = null)
    {
        return $this->toOptionArray($method);
    }

    /**
     * Return array of options as value-label pairs
     *
     * @param string|null $method
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     * @throws BuckarooException
     */
    public function toOptionArray(?string $method = null): array
    {
        $currencies = $this->allowedCurrenciesConfig->getAllowedCurrencies();
        if ($method) {
            $methodConfig = $this->configProviderMethodFactory->get($method);
            $currencies = $methodConfig->getBaseAllowedCurrencies();
        }

        $locale = $this->localeResolver->getLocale();
        $translatedCurrencies = $this->currencyBundle->get($locale)['Currencies'] ?: [];

        $output = [];
        foreach ($currencies as $currency) {
            $output[] = [
                'value' => $currency,
                'label' => $translatedCurrencies[$currency][1] ?? $currency,
            ];
        }

        asort($output);

        return $output;
    }
}
