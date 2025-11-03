<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Data;

use Buckaroo\Magento2\Api\Data\AnalyticsExtensionInterface;
use Buckaroo\Magento2\Api\Data\AnalyticsInterface;
use Magento\Framework\Api\AbstractExtensibleObject;

class Analytics extends AbstractExtensibleObject implements AnalyticsInterface
{
    /**
     * @inheritdoc
     */
    public function getAnalyticsId()
    {
        return $this->_get(self::ANALYTICS_ID);
    }

    /**
     * @inheritdoc
     */
    public function setAnalyticsId($analyticsId)
    {
        return $this->setData(self::ANALYTICS_ID, $analyticsId);
    }

    /**
     * @inheritdoc
     */
    public function getQuoteId()
    {
        return $this->_get(self::QUOTE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritdoc
     */
    public function getClientId()
    {
        return $this->_get(self::CLIENT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setClientId($clientId)
    {
        return $this->setData(self::CLIENT_ID, $clientId);
    }

    /**
     * @inheritdoc
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * @inheritdoc
     */
    public function setExtensionAttributes(AnalyticsExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
