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

namespace TIG\Buckaroo\Model\Plugin\Method;

/**
 * Class Afterpay2
 *
 * @package TIG\Buckaroo\Model\Plugin\Method
 */
class Afterpay2
{
    const AFTERPAY_METHOD_NAME = 'tig_buckaroo_afterpay2';

    /**
     * \TIG\Buckaroo\Model\Method\Afterpay2
     *
     * @var bool
     */
    public $afterpayMethod = false;

    /**
     * @param \TIG\Buckaroo\Model\Method\Afterpay2 $afterpay
     */
    public function __construct(\TIG\Buckaroo\Model\Method\Afterpay2 $afterpay)
    {
        $this->afterpayMethod = $afterpay;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     *
     * @return \Magento\Sales\Model\Order
     */
    public function afterCancel(
        \Magento\Sales\Model\Order $subject
    ) {
        $payment = $subject->getPayment();
        $orderIsCanceled = $payment->getOrder()->isCanceled();
        $orderIsVoided = ($payment->getAdditionalInformation('voided_by_buckaroo') === true);

        if ($payment->getMethod() !== self::AFTERPAY_METHOD_NAME || $orderIsCanceled || $orderIsVoided) {
            return $subject;
        }

        $this->afterpayMethod->cancel($payment);

        return $this;
    }
}
