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

namespace Buckaroo\Magento2\Controller\Onepage;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\AbstractMethod;

/**
 * Override Onepage checkout success controller class
 */
class Success extends \Magento\Checkout\Controller\Onepage\Success {

    /** 
     * @inheritDoc
     * If the user visits the payment complete page when doing a payment
     * or when the order is canceled redirect to cart
     */
    public function execute() 
    {
        
        $order = $this->getOnepage()->getCheckout()->getLastRealOrder();
        $payment = $order->getPayment();

        if(
            $this->isBuckarooPayment($payment) &&
            (
                ($order->getStatus() === 'pending' &&  $this->paymentInTransit($payment)) ||
                $order->getStatus() === 'canceled'
            )
        ) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        return parent::execute();
    }

    /**
     * Is one of our payment methods
     *
     * @param OrderPaymentInterface $payment
     *
     * @return boolean
     */
    public function isBuckarooPayment(OrderPaymentInterface $payment)
    {
        return strpos($payment->getMethod(), 'buckaroo_magento2') !== false;
    }
    /**
     * Check if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     *
     * @return boolean
     */
    protected function paymentInTransit(OrderPaymentInterface $payment)
    {
        return $payment->getAdditionalInformation(AbstractMethod::BUCKAROO_PAYMENT_IN_TRANSIT) === true;
    }
}