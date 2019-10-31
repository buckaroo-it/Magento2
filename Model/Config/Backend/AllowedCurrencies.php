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
namespace TIG\Buckaroo\Model\Config\Backend;

/**
 * @method mixed getValue()
 */
class AllowedCurrencies extends \Magento\Framework\App\Config\Value
{
    /**
     * @var \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies
     */
    protected $configProvider;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Framework\Locale\Bundle\CurrencyBundle
     */
    protected $currencyBundle;

    /**
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $config
     * @param \Magento\Framework\App\Cache\TypeListInterface               $cacheTypeList
     * @param \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies         $configProvider
     * @param \Magento\Framework\Locale\Bundle\CurrencyBundle              $currencyBundle
     * @param \Magento\Framework\Locale\ResolverInterface                  $localeResolver
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \TIG\Buckaroo\Model\ConfigProvider\AllowedCurrencies $configProvider,
        \Magento\Framework\Locale\Bundle\CurrencyBundle $currencyBundle,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->configProvider = $configProvider;
        $this->currencyBundle = $currencyBundle;
        $this->localeResolver = $localeResolver;
    }

    /**
     * Check that the value contains valid currencies.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save()
    {
        $value = (array)$this->getValue();
        $allowedCurrencies = $this->configProvider->getAllowedCurrencies();

        foreach ($value as $currency) {
            if (!in_array($currency, $allowedCurrencies)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Please enter a valid currency: '%1'.", $this->getCurrencyTranslation($currency))
                );
            }
        }

        return parent::save();
    }

    /**
     * Checks if there is a translation for this currency. If not, returns the original value to show it to the user.
     *
     * @param $currency
     *
     * @return mixed
     */
    protected function getCurrencyTranslation($currency)
    {
        $output = $currency;
        $locale = $this->localeResolver->getLocale();
        $translatedCurrencies = $this->currencyBundle->get($locale)['Currencies'] ?: [];

        if (array_key_exists($currency, $translatedCurrencies)) {
            $output = $translatedCurrencies[$currency][1];
        }

        return $output;
    }
}
