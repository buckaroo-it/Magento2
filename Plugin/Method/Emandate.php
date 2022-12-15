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

namespace Buckaroo\Magento2\Plugin\Method;

use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Sales\Model\Order;

class Emandate
{
    /**
     * @var BuckarooAdapter
     */
    private BuckarooAdapter $paymentMethod;

    /**
     * @param BuckarooAdapter $paymentMethod
     */
    public function __construct(BuckarooAdapter $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Set CanCreditmemo true for Emandate
     *
     * @param Order $subject
     * @return Order
     */
    public function beforeCanCreditmemo(Order $subject)
    {
        $payment = $subject->getPayment();

        if ($payment->getMethod() === $this->paymentMethod->getCode()) {
            $subject->setForcedCanCreditmemo($this->paymentMethod->canRefund());
        }

        return $subject;
    }
}
