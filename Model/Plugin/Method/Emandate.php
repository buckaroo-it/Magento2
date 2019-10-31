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
class Emandate
{
    const EMANDATE_METHOD_NAME = 'tig_buckaroo_emandate';

    /**
     * \TIG\Buckaroo\Model\Method\Emandate
     *
     * @var bool
     */
    public $emandateMethod = false;

    /**
     * @param \TIG\Buckaroo\Model\Method\Klarna $klarna
     */
    public function __construct(\TIG\Buckaroo\Model\Method\Emandate $emandate)
    {
        $this->emandateMethod = $emandate;
    }

    /**
     * @param Order $subject
     *
     * @return Order
     */
    public function beforeCanCreditmemo(Order $subject)
    {
        $payment = $subject->getPayment();

        if ($payment->getMethod() === self::EMANDATE_METHOD_NAME) {
            $subject->setForcedCanCreditmemo($this->emandateMethod->canRefund());
        }

        return $subject;
    }
}
