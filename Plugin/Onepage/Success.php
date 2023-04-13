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

namespace Buckaroo\Magento2\Plugin\Onepage;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Helper\Data as BuckarooDataHelper;
use Magento\Checkout\Controller\Onepage\Success as ControllerOnePageSuccess;

/**
 * Override One page checkout success controller class
 */
class Success
{
    /**
     * @var RedirectFactory
     */
    protected RedirectFactory $resultRedirectFactory;

    /**
     * @var Log
     */
    protected Log $logger;

    /**
     * @param Context $context
     * @param Log $logger
     */
    public function __construct(
        Context $context,
        Log $logger
    ) {
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->logger = $logger;
    }

    /**
     * If the user visits the payment complete page when doing a payment or when the order is canceled redirect to cart
     *
     * @param ControllerOnePageSuccess $checkoutSuccess
     * @param callable $proceed
     * @return Redirect
     */
    public function aroundExecute(ControllerOnePageSuccess $checkoutSuccess, callable $proceed): Redirect
    {
        $order = $checkoutSuccess->getOnepage()->getCheckout()->getLastRealOrder();
        $payment = $order->getPayment();

        $this->logger->addDebug(
            var_export([
                $order->getStatus() === BuckarooDataHelper::M2_ORDER_STATE_PENDING,
                $this->paymentInTransit($payment),
                $order->getStatus() === Order::STATE_CANCELED
            ], true)
        );

        if ($this->isBuckarooPayment($payment) &&
            (
                ($order->getStatus() === BuckarooDataHelper::M2_ORDER_STATE_PENDING
                    && $this->paymentInTransit($payment)
                )
                || $order->getStatus() === Order::STATE_CANCELED
            )
        ) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        return $proceed();
    }

    /**
     * Check if user is on the payment provider page
     *
     * @param OrderPaymentInterface|null $payment
     * @return boolean
     */
    protected function paymentInTransit(OrderPaymentInterface $payment = null): bool
    {
        if ($payment === null) {
            return false;
        }

        return $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_PAYMENT_IN_TRANSIT) == true;
    }

    /**
     * Is one of our payment methods
     *
     * @param OrderPaymentInterface|null $payment
     * @return boolean
     */
    public function isBuckarooPayment(?OrderPaymentInterface $payment): bool
    {
        if (!$payment instanceof OrderPaymentInterface) {
            return false;
        }
        return strpos($payment->getMethod(), 'buckaroo_magento2') !== false;
    }
}