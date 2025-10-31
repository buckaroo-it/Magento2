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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies as ConfigAllowedCurrencies;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Bundle\CurrencyBundle;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * @method mixed getValue()
 */
class AllowedCurrencies extends Value
{
    /**
     * @var ConfigAllowedCurrencies
     */
    protected $configProvider;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var CurrencyBundle
     */
    protected $currencyBundle;

    /**
     * @param Context                 $context
     * @param Registry                $registry
     * @param ScopeConfigInterface    $config
     * @param TypeListInterface       $cacheTypeList
     * @param ConfigAllowedCurrencies $configProvider
     * @param CurrencyBundle          $currencyBundle
     * @param ResolverInterface       $localeResolver
     * @param AbstractResource|null   $resource
     * @param AbstractDb|null         $resourceCollection
     * @param array                   $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ConfigAllowedCurrencies $configProvider,
        CurrencyBundle $currencyBundle,
        ResolverInterface $localeResolver,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
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
     * @throws LocalizedException
     * @return $this
     */
    public function save()
    {
        $value = (array)$this->getValue();
        $allowedCurrencies = $this->configProvider->getAllowedCurrencies();

        foreach ($value as $currency) {
            if (!in_array($currency, $allowedCurrencies)) {
                throw new LocalizedException(
                    __("Please enter a valid currency: '%1'.", $this->getCurrencyTranslation($currency))
                );
            }
        }

        return parent::save();
    }

    /**
     * Checks if there is a translation for this currency. If not, returns the original value to show it to the user.
     *
     * @param  string $currency
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
