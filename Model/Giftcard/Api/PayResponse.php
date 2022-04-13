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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use \Magento\Framework\DataObject;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;

class PayResponse extends DataObject implements PayResponseInterface
{
    /**
     * Get RemainderAmount
     *
     * @api
     * @return string
     */
    public function getRemainderAmount()
    {
        return $this->getData('remainderAmount');
    }
    /**
     * Get AlreadyPaid
     *
     * @api
     * @return string
     */
    public function getAlreadyPaid()
    {
        return $this->getData('alreadyPaid');
    }
    /**
     * Get error
     *
     * @api
     * @return string
     */
    public function getError()
    {
        return $this->getData('error');
    }
    /**
     * Set error
     *
     * @param string $error
     *
     * @return void
     */
    public function setError(string $error)
    {
        $this->setData('error', $error);
    }
    /**
     * Has error
     *
     * @return boolean
     */
    public function hasError()
    {
        return $this->getError() != null;
    }
}
