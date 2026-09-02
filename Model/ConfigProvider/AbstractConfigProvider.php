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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

abstract class AbstractConfigProvider implements ConfigProviderInterface, ConfigInterface
{
    protected const DEFAULT_PATH_PATTERN = 'payment/%s/%s';

    /**
     * @var string
     */
    protected $xpathPrefix = 'XPATH_';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var string|null
     */
    protected $methodCode;

    /**
     * @var string|null
     */
    protected $pathPattern;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string|null          $methodCode
     * @param string               $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ?string $methodCode = null,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->methodCode = $methodCode;
        $this->pathPattern = $pathPattern;
    }

    /**
     * Get all config in associated array
     *
     * @return array
     */
    public function getConfig()
    {
        return [];
    }

    /**
     * Set the Xpath Prefix
     *
     * @param string $xpathPrefix
     *
     * @return $this
     */
    public function setXpathPrefix($xpathPrefix)
    {
        $this->xpathPrefix = $xpathPrefix;
        return $this;
    }

    /**
     * Return Xpath Prefix
     *
     * @return string
     */
    public function getXpathPrefix()
    {
        return $this->xpathPrefix;
    }

    /**
     * Return the config value for the given Xpath (optionally with $store)
     *
     * @param string         $xpath
     * @param null|int|Store $store
     *
     * @return mixed
     */
    protected function getConfigFromXpath(string $xpath, $store = null)
    {
        return $this->scopeConfig->getValue(
            $xpath,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Sets method code
     *
     * @param string $methodCode
     */
    public function setMethodCode($methodCode)
    {
        $this->methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string   $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null)
    {
        if ($this->methodCode === null || $this->pathPattern === null) {
            return null;
        }

        return $this->scopeConfig->getValue(
            sprintf($this->pathPattern, $this->methodCode, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
