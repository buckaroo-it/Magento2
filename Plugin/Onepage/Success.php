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

namespace Buckaroo\Magento2\Plugin\Onepage;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Framework\App\Action\Context;

/**
 * Override Onepage checkout success controller class
 */
class Success {

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    // /**
    //  * @var \Magento\Checkout\Model\Session
    //  */
    // protected $checkoutSession;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
        // \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        // $this->checkoutSession = $checkoutSession;
    }
    /** 
     * If the user visits the payment complete page when doing a payment
     * or when the order is canceled redirect to cart
     */
    public function aroundExecute(\Magento\Checkout\Controller\Onepage\Success $checkoutSuccess, callable $proceed) 
    {
       
        $order = $checkoutSuccess->getOnepage()->getCheckout()->getLastRealOrder();
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
        return $proceed();
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