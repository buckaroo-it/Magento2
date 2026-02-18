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
use Magento\Framework\Encryption\EncryptorInterface;

class ExternalEmailProvider
{
    public const XPATH_ENABLED = 'buckaroo_magento2/second_chance/external_email_enabled';
    public const XPATH_METHOD = 'buckaroo_magento2/second_chance/external_email_method';
    public const XPATH_PROVIDER_NAME = 'buckaroo_magento2/second_chance/external_email_provider';

    // SMTP Configuration
    public const XPATH_SMTP_HOST = 'buckaroo_magento2/second_chance/external_email_smtp_host';
    public const XPATH_SMTP_PORT = 'buckaroo_magento2/second_chance/external_email_smtp_port';
    public const XPATH_SMTP_ENCRYPTION = 'buckaroo_magento2/second_chance/external_email_smtp_encryption';
    public const XPATH_SMTP_USERNAME = 'buckaroo_magento2/second_chance/external_email_smtp_username';
    public const XPATH_SMTP_PASSWORD = 'buckaroo_magento2/second_chance/external_email_smtp_password';

    // API Configuration
    public const XPATH_API_ENDPOINT = 'buckaroo_magento2/second_chance/external_email_api_endpoint';
    public const XPATH_API_KEY = 'buckaroo_magento2/second_chance/external_email_api_key';
    public const XPATH_API_AUTH_TYPE = 'buckaroo_magento2/second_chance/external_email_api_auth_type';

    // Email Settings
    public const XPATH_FROM_EMAIL = 'buckaroo_magento2/second_chance/external_email_from_email';
    public const XPATH_FROM_NAME = 'buckaroo_magento2/second_chance/external_email_from_name';
    public const XPATH_REPLY_TO = 'buckaroo_magento2/second_chance/external_email_reply_to';

    // Error Handling
    public const XPATH_RETRY_ENABLED = 'buckaroo_magento2/second_chance/external_email_retry_enabled';
    public const XPATH_RETRY_ATTEMPTS = 'buckaroo_magento2/second_chance/external_email_retry_attempts';
    public const XPATH_FALLBACK_ENABLED = 'buckaroo_magento2/second_chance/external_email_fallback_enabled';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface   $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * Check if external email provider is enabled for second-chance emails
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get email sending method (smtp or api)
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getMethod($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_METHOD,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 'smtp';
    }

    /**
     * Get provider name for logging/identification
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getProviderName($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_PROVIDER_NAME,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 'External Provider';
    }

    /**
     * Get SMTP host
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSmtpHost($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_SMTP_HOST,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get SMTP port
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getSmtpPort($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_SMTP_PORT,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 587;
    }

    /**
     * Get SMTP encryption (none, ssl, tls)
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSmtpEncryption($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_SMTP_ENCRYPTION,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 'tls';
    }

    /**
     * Get SMTP username
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSmtpUsername($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_SMTP_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get SMTP password
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSmtpPassword($store = null): string
    {
        $encryptedPassword = $this->scopeConfig->getValue(
            self::XPATH_SMTP_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $encryptedPassword ? $this->encryptor->decrypt($encryptedPassword) : '';
    }

    /**
     * Get API endpoint URL
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getApiEndpoint($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_API_ENDPOINT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get API key/token
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getApiKey($store = null): string
    {
        $encryptedKey = $this->scopeConfig->getValue(
            self::XPATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $encryptedKey ? $this->encryptor->decrypt($encryptedKey) : '';
    }

    /**
     * Get API authentication type (bearer, basic, api_key_header, etc.)
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getApiAuthType($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_API_AUTH_TYPE,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 'bearer';
    }

    /**
     * Get from email address
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getFromEmail($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_FROM_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get from name
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getFromName($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_FROM_NAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get reply-to email address
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getReplyTo($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_REPLY_TO,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if retry is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isRetryEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_RETRY_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get number of retry attempts
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getRetryAttempts($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_RETRY_ATTEMPTS,
            ScopeInterface::SCOPE_STORE,
            $store
        ) ?: 3;
    }

    /**
     * Check if fallback to Magento mail is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isFallbackEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_FALLBACK_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
