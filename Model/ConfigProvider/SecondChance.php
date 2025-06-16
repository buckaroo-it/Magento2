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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SecondChance
{
    const XPATH_SECOND_CHANCE_ENABLED = 'buckaroo_magento2/second_chance/enable_second_chance';
    const XPATH_SECOND_CHANCE_EMAIL1_ENABLED = 'buckaroo_magento2/second_chance/second_chance_email';
    const XPATH_SECOND_CHANCE_EMAIL2_ENABLED = 'buckaroo_magento2/second_chance/second_chance_email2';
    const XPATH_SECOND_CHANCE_TEMPLATE1 = 'buckaroo_magento2/second_chance/second_chance_template';
    const XPATH_SECOND_CHANCE_TEMPLATE2 = 'buckaroo_magento2/second_chance/second_chance_template2';
    const XPATH_SECOND_CHANCE_TIMING1 = 'buckaroo_magento2/second_chance/second_chance_timing';
    const XPATH_SECOND_CHANCE_TIMING2 = 'buckaroo_magento2/second_chance/second_chance_timing2';
    const XPATH_NO_SEND_OUT_OF_STOCK = 'buckaroo_magento2/second_chance/no_send_second_chance';
    const XPATH_PRUNE_DAYS = 'buckaroo_magento2/second_chance/prune_days';
    const XPATH_MULTIPLE_EMAILS_SEND = 'buckaroo_magento2/second_chance/multiple_emails_send';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if SecondChance is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return bool
     */
    public function isSecondChanceEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if first email is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return bool
     */
    public function isFirstEmailEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_EMAIL1_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if second email is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return bool
     */
    public function isSecondEmailEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_EMAIL2_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get first email template
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return string
     */
    public function getFirstEmailTemplate($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TEMPLATE1,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get second email template
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return string
     */
    public function getSecondEmailTemplate($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TEMPLATE2,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get first email timing in hours
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return int
     */
    public function getFirstEmailTiming($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TIMING1,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get second email timing in hours
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return int
     */
    public function getSecondEmailTiming($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TIMING2,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if sending should be skipped for out of stock products
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return bool
     */
    public function shouldSkipOutOfStock($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_NO_SEND_OUT_OF_STOCK,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get prune days setting
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return int
     */
    public function getPruneDays($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_PRUNE_DAYS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if multiple emails can be sent at once
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     * @return bool
     */
    public function canSendMultipleEmails($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_MULTIPLE_EMAILS_SEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
} 