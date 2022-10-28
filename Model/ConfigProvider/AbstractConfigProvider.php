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
namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Store\Model\ScopeInterface;

abstract class AbstractConfigProvider implements ConfigProviderInterface, ConfigInterface
{
    const DEFAULT_PATH_PATTERN = 'payment/%s/%s';

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
     * @param null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->methodCode = $methodCode;
        $this->pathPattern = $pathPattern;
    }

    /**
     * Get all config in associated array
     *
     * @param  null|int|\Magento\Store\Model\Store $store
     * @return array
     */
    public function getConfig()
    {
        return [];
    }

    /**
     * Set the Xpath Prefix
     *
     * @param $xpathPrefix
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
     * @param string                              $xpath
     * @param null|int|\Magento\Store\Model\Store $store
     *
     * @return mixed
     */
    protected function getConfigFromXpath($xpath, $store = null)
    {
        return $this->scopeConfig->getValue(
            $xpath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Sets method code
     *
     * @param string $methodCode
     * @return void
     */
    public function setMethodCode($methodCode)
    {
        $this->methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     * @return void
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
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
