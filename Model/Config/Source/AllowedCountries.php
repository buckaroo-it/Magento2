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
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Locale\Bundle\RegionBundle;
use Magento\Framework\Locale\ListsInterface;
use Magento\Framework\Locale\ResolverInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;

class AllowedCountries implements OptionSourceInterface
{
    /**
     * @var ListsInterface
     */
    private $localeLists;

    /**
     * @var ConfigProviderFactory
     */
    private $configProviderMethodFactory;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var RegionBundle
     */
    protected $regionBundle;

    /**
     * @param ListsInterface        $localeLists
     * @param ResolverInterface     $localeResolver
     * @param ConfigProviderFactory $configProviderMethodFactory
     * @param RegionBundle          $regionBundle
     */
    public function __construct(
        ListsInterface $localeLists,
        ResolverInterface $localeResolver,
        ConfigProviderFactory $configProviderMethodFactory,
        RegionBundle $regionBundle
    ) {
        $this->localeLists = $localeLists;
        $this->localeResolver = $localeResolver;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->regionBundle = $regionBundle;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @param string|null $method
     *
     * @throws BuckarooException
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(?string $method = null): array
    {
        if (!$method || !is_string($method)) {
            return $this->localeLists->getOptionCountries();
        }

        $methodConfig = $this->configProviderMethodFactory->get($method);
        $countries = $methodConfig->getBaseAllowedCountries();

        $locale = $this->localeResolver->getLocale();
        $translatedCountries = $this->regionBundle->get($locale)['Countries'] ?: [];

        $output = [];

        if (is_array($countries)) {
            foreach ($countries as $country) {
                $output[] = [
                    'value' => $country,
                    'label' => $translatedCountries[$country],
                ];
            }
        }
        asort($output);

        return $output;
    }

    /**
     * $method is what is defined in system.xml (i.e. ::ideal) and is directly passed to toOptionArray for method
     * configuration exemptions.
     *
     * @param string     $method
     * @param array|null $params
     *
     * @throws BuckarooException
     *
     * @return array
     */
    public function __call(string $method, ?array $params = null)
    {
        return $this->toOptionArray($method);
    }
}
