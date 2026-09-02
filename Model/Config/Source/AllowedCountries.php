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
     * Handle dynamic calls for methods defined in system.xml (i.e. :ideal).
     *
     * The called method name is directly passed to toOptionArray for method
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
