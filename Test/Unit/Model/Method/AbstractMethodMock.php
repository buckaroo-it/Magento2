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

namespace TIG\Buckaroo\Test\Unit\Model\Method;

/**
 * Class AbstractMethodMock
 *
 * @package TIG\Buckaroo\Test\Unit\Model\Method
 */
class AbstractMethodMock extends \TIG\Buckaroo\Model\Method\AbstractMethod
{
    // @codingStandardsIgnoreStart
    protected $_code = 'tig_buckaroo_test';
    // @codingStandardsIgnoreEnd

    public function getOrderTransactionBuilder($payment)
    {
    }

    public function getAuthorizeTransactionBuilder($payment)
    {
    }

    public function getCaptureTransactionBuilder($payment)
    {
    }

    public function getRefundTransactionBuilder($payment)
    {
    }

    public function getVoidTransactionBuilder($payment)
    {
    }

    public function setCanRefund($value)
    {
        $this->_canRefund = $value;
    }

    public function setCanVoid($value)
    {
        $this->_canVoid = $value;
    }

    public function setCanOrder($value)
    {
        $this->_canOrder = $value;
    }

    public function setCanAuthorize($value)
    {
        $this->_canAuthorize = $value;
    }

    public function setCanCapture($value)
    {
        $this->_canCapture = $value;
    }

    public function setEventManager($eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    public function getCode()
    {
        return $this->_code;
    }
}
