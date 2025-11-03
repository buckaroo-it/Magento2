<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

interface AnalyticsInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    public const QUOTE_ID = 'quote_id';
    public const ANALYTICS_ID = 'analytics_id';
    public const CLIENT_ID = 'client_id';

    /**
     * Get analytics_id
     *
     * @return string|null
     */
    public function getAnalyticsId();

    /**
     * Set analytics_id
     *
     * @param string $analyticsId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setAnalyticsId($analyticsId);

    /**
     * Get quote_id
     *
     * @return string|null
     */
    public function getQuoteId();

    /**
     * Set quote_id
     *
     * @param string $quoteId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setQuoteId($quoteId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     * @param \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface $extensionAttributes
    );

    /**
     * Get client_id
     *
     * @return string|null
     */
    public function getClientId();

    /**
     * Set client_id
     *
     * @param string $clientId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setClientId($clientId);
}
