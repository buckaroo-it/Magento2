<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Model\Config\Source;

class AllowedCurrencies implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies
     */
    protected $allowedCurrenciesConfig;

    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var \Magento\Framework\Locale\TranslatedLists
     */
    protected $listModels;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Framework\Locale\Bundle\CurrencyBundle
     */
    protected $currencyBundle;

    /**
     * @param \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies $allowedCurrenciesConfig
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Factory    $configProviderMethodFactory
     * @param \Magento\Framework\Locale\Bundle\CurrencyBundle      $currencyBundle
     * @param \Magento\Framework\Locale\ResolverInterface          $localeResolver
     * @param \Magento\Framework\Locale\TranslatedLists            $listModels
     */
    public function __construct(
        \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies $allowedCurrenciesConfig,
        \TIG\Buckaroo\Model\ConfigProvider\Method\Factory $configProviderMethodFactory,
        \Magento\Framework\Locale\Bundle\CurrencyBundle $currencyBundle,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Locale\TranslatedLists $listModels
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->allowedCurrenciesConfig = $allowedCurrenciesConfig;
        $this->currencyBundle = $currencyBundle;
        $this->localeResolver = $localeResolver;
        $this->listModels = $listModels;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @param null $method
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     * @throws \TIG\Buckaroo\Exception
     */
    public function toOptionArray($method = null)
    {
        $currencies = $this->allowedCurrenciesConfig->getAllowedCurrencies();
        if ($method) {
            /**
             * @var \TIG\Buckaroo\Model\ConfigProvider\Method\ConfigProviderInterface $methodConfig
             */
            $methodConfig = $this->configProviderMethodFactory->get($method);
            $currencies = $methodConfig->getBaseAllowedCurrencies();
        }

        $locale = $this->localeResolver->getLocale();
        $translatedCurrencies = $this->currencyBundle->get($locale)['Currencies'] ?: [];

        $output = [];
        foreach ($currencies as $currency) {
            $output[] = [
                'value' => $currency,
                'label' => $translatedCurrencies[$currency][1],
            ];
        }

        asort($output);

        return $output;
    }

    /**
     * $method is what is defined in system.xml (i.e. ::ideal) and is directly passed to toOptionArray for method
     * configuration exemptions.
     *
     * @param $method
     * @param null   $params
     *
     * @return array
     */
    public function __call($method, $params = null)
    {
        return $this->toOptionArray($method);
    }
}
