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
    private const XML_PATH_SECOND_CHANCE_ENABLE_SECOND_CHANCE
        = 'buckaroo_magento2/second_chance/enable_second_chance';

    private const XML_PATH_SECOND_CHANCE_EMAIL
        = 'buckaroo_magento2/second_chance/email';

    private const XML_PATH_SECOND_CHANCE_EMAIL2
        = 'buckaroo_magento2/second_chance/email2';

    private const XML_PATH_SECOND_CHANCE_TEMPLATE
        = 'buckaroo_magento2/second_chance/second_chance_template';

    private const XML_PATH_SECOND_CHANCE_TEMPLATE2
        = 'buckaroo_magento2/second_chance/second_chance_template2';

    private const XML_PATH_SECOND_CHANCE_DEFAULT_TEMPLATE = 'buckaroo_second_chance';
    
    private const XML_PATH_SECOND_CHANCE_DEFAULT_TEMPLATE2 = 'buckaroo_second_chance2';

    private const XML_PATH_SECOND_CHANCE_TIMING
        = 'buckaroo_magento2/second_chance/second_chance_timing';
    
    private const XML_PATH_SECOND_CHANCE_TIMING2
        = 'buckaroo_magento2/second_chance/second_chance_timing2';

    private const XML_PATH_SECOND_CHANCE_PRUNE_DAYS
        = 'buckaroo_magento2/second_chance/prune_days';

    private const XML_PATH_SECOND_CHANCE_NO_SEND
        = 'buckaroo_magento2/second_chance/no_send_second_chance';

    private const XML_PATH_SECOND_FINAL_STATUS = 10;

    /**
     * @var ScopeConfigInterface
     */
    private $storeConfig;

    public function __construct(ScopeConfigInterface $storeConfig)
    {
        $this->storeConfig = $storeConfig;
    }

    public function isSecondChanceEnabled($store = null): bool
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_ENABLE_SECOND_CHANCE,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (bool) $config;
    }

    public function getSecondChancePruneDays($store = null): string
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_PRUNE_DAYS,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (string) $config;
    }

    public function getFinalStatus(): string
    {
        return static::XML_PATH_SECOND_FINAL_STATUS;
    }

    public function isSecondChanceEmail($store = null): bool
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_EMAIL,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (bool) $config;
    }

    public function isSecondChanceEmail2($store = null): bool
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_EMAIL2,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (bool) $config;
    }

    public function getSecondChanceTiming($store = null): string
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_TIMING,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (string) $config;
    }

    public function getSecondChanceTiming2($store = null): string
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_TIMING2,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (string) $config;
    }

    public function getSecondChanceTemplate($store = null): string
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_TEMPLATE,
            ScopeInterface::SCOPE_STORES,
            $store
        ) ?? self::XML_PATH_SECOND_CHANCE_DEFAULT_TEMPLATE;
        return (string) $config;
    }

    public function getSecondChanceTemplate2($store = null): string
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_TEMPLATE2,
            ScopeInterface::SCOPE_STORES,
            $store
        ) ?? self::XML_PATH_SECOND_CHANCE_DEFAULT_TEMPLATE2;
        return (string) $config;
    }

    public function getFromEmail($store = null): string
    {
        $config = $this->storeConfig->getValue(
            'trans_email/ident_sales/email',
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return (string) $config;
    }
    
    public function getFromName($store = null): string
    {
        $config = $this->storeConfig->getValue(
            'trans_email/ident_sales/name',
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return (string) $config;
    }

    public function getNoSendSecondChance($store = null): bool
    {
        $config = $this->storeConfig->getValue(
            static::XML_PATH_SECOND_CHANCE_NO_SEND,
            ScopeInterface::SCOPE_STORES,
            $store
        );
        return (bool) $config;
    }
}
