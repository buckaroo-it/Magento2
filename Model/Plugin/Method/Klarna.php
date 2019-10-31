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

use \Magento\Sales\Model\Order;

/**
 * Class Klarna
 *
 * @package TIG\Buckaroo\Model\Plugin\Method
 */
class Klarna
{
    const KLARNA_METHOD_NAME = 'tig_buckaroo_klarna';

    /**
     * \TIG\Buckaroo\Model\Method\Klarna
     *
     * @var bool
     */
    public $klarnaMethod = false;

    /**
     * @param \TIG\Buckaroo\Model\Method\Klarna $klarna
     */
    public function __construct(\TIG\Buckaroo\Model\Method\Klarna $klarna)
    {
        $this->klarnaMethod = $klarna;
    }

    /**
     * @param Order $subject
     *
     * @return Klarna|Order
     * @throws \TIG\Buckaroo\Exception
     */
    public function afterCancel(
        Order $subject
    ) {
        $payment = $subject->getPayment();
        $orderIsCanceled = $payment->getOrder()->getOrigData('state');
        $orderIsVoided = ($payment->getAdditionalInformation('voided_by_buckaroo') === true);

        if ($payment->getMethod() !== self::KLARNA_METHOD_NAME || $orderIsVoided || $orderIsCanceled == Order::STATE_CANCELED) {
            return $subject;
        }

        $this->klarnaMethod->cancel($payment);

        return $this;
    }
}
