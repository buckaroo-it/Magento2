<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Api\Data;

interface AnalyticsInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{

    const QUOTE_ID = 'quote_id';
    const ANALYTICS_ID = 'analytics_id';
    const CLIENT_ID = 'client_id';

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
     *
     * @return AnalyticsInterface
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
     *
     * @return AnalyticsInterface
     */
    public function setQuoteId($quoteId);

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return AnalyticsExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param AnalyticsExtensionInterface $extensionAttributes
     *
     * @return $this
     */
    public function setExtensionAttributes(
        AnalyticsExtensionInterface $extensionAttributes
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
     *
     * @return AnalyticsInterface
     */
    public function setClientId($clientId);
}
