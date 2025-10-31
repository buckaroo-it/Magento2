<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Analytics\Data;

use Buckaroo\Magento2\Api\Data\AnalyticsInterface;

class Analytics extends \Magento\Framework\Api\AbstractExtensibleObject implements AnalyticsInterface
{

    /**
     * Get analytics_id
     * @return string|null
     */
    public function getAnalyticsId()
    {
        return $this->_get(self::ANALYTICS_ID);
    }

    /**
     * Set analytics_id
     * @param  string                                         $analyticsId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setAnalyticsId($analyticsId)
    {
        return $this->setData(self::ANALYTICS_ID, $analyticsId);
    }

    /**
     * Get quote_id
     * @return string|null
     */
    public function getQuoteId()
    {
        return $this->_get(self::QUOTE_ID);
    }

    /**
     * Set quote_id
     * @param  string                                         $quoteId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     * @param  \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface $extensionAttributes
    ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Get client_id
     * @return string|null
     */
    public function getClientId()
    {
        return $this->_get(self::CLIENT_ID);
    }

    /**
     * Set client_id
     * @param  string                                         $clientId
     * @return \Buckaroo\Magento2\Api\Data\AnalyticsInterface
     */
    public function setClientId($clientId)
    {
        return $this->setData(self::CLIENT_ID, $clientId);
    }
}
