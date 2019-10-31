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
namespace TIG\Buckaroo\Model\ConfigProvider;

use \Magento\Checkout\Model\ConfigProviderInterface;

abstract class AbstractConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    protected $xpathPrefix = 'XPATH_';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Allows getSomethingValue calls to be turned into XPATH names and returns the value IF they exist on the
     * extending child class.
     *
     * @param string $method
     * @param mixed  $params
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     */
    public function __call($method, $params)
    {
        /**
         * By default, assume there's no constant
         */
        $constant = null;

        /**
         * If there's a param, it has to be either a Store object or a store id. Either way, we just pass it on as is
         */
        $store = null;
        if (isset($params[0])) {
            $store = $params[0];
        }

        /**
         * Check if the store parameter is valid.
         */
        if ($store && !is_int($store) && !$store instanceof \Magento\Store\Model\Store) {
            throw new \InvalidArgumentException(
                "First argument passed to the getter should be an integer or an instance of" .
                " '\\Magento\\Store\\Model\\Store"
            );
        }

        /**
         * If $method starts with get, we've got a contender
         */
        if (substr($method, 0, 3) === 'get') {
            /**
             * Remove get from the method name
             */
            $camel = substr($method, 3);
            /**
             * And turn CamelCasedValue into Camel_Cased_Value
             */
            $camelScored = preg_replace(
                '/(^[^A-Z]+|[A-Z][^A-Z]+)/',
                '_$1',
                $camel
            );

            /**
             * Get the actual class name
             */
            $class = get_class($this);
            $classParts = explode('\\', $class);
            $className = end($classParts);

            /**
             * Uppercase and append it to the XPATH prefix & child class' name
             */
            $constant = strtoupper('static::' . $this->getXpathPrefix() . $className . $camelScored);
        }
        if ($constant && defined($constant) && !empty(constant($constant))) {
            return $this->getConfigFromXpath(constant($constant), $store);
        }
        return null;
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
}
