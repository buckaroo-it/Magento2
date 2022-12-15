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

use Magento\Sales\Model\Order;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Helper\Data as BuckarooDataHelper;

/**
 * Override Onepage checkout success controller class
 */
class Success
{
    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
    }

    /**
     * If the user visits the payment complete page when doing a payment
     * or when the order is canceled redirect to cart
     */
    public function aroundExecute(\Magento\Checkout\Controller\Onepage\Success $checkoutSuccess, callable $proceed)
    {

        $order = $checkoutSuccess->getOnepage()->getCheckout()->getLastRealOrder();
        $payment = $order->getPayment();

        if ($this->isBuckarooPayment($payment) &&
            (
                ($order->getStatus() === BuckarooDataHelper::M2_ORDER_STATE_PENDING &&  $this->paymentInTransit($payment)) ||
                $order->getStatus() === Order::STATE_CANCELED
            )
        ) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        return $proceed();
    }

    /**
     * Is one of our payment methods
     *
     * @param OrderPaymentInterface|null $payment
     *
     * @return boolean
     */
    public function isBuckarooPayment($payment)
    {
        if (!$payment instanceof OrderPaymentInterface) {
            return false;
        }
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
        return $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_PAYMENT_IN_TRANSIT) === true;
    }
}
