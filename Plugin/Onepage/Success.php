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

use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Sales\Model\Order;
use Buckaroo\Magento2\Logging\Log;
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
     * @var Log
     */
    protected $logger;

    /**
     * @var CheckPaymentType
     */
    protected $checkPaymentType;

    /**
     * @param Context $context
     * @param Log $logger
     * @param CheckPaymentType $checkPaymentType
     */
    public function __construct(
        Context $context,
        Log $logger,
        CheckPaymentType $checkPaymentType
    ) {
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->logger = $logger;
        $this->checkPaymentType = $checkPaymentType;
    }
    
    /**
     * If the user visits the payment complete page when doing a payment or when the order is canceled redirect to cart
     */
    public function aroundExecute(\Magento\Checkout\Controller\Onepage\Success $checkoutSuccess, callable $proceed)
    {

        $order = $checkoutSuccess->getOnepage()->getCheckout()->getLastRealOrder();
        $payment = $order->getPayment();

        $this->logger->addDebug(
            var_export([
                $order->getStatus() === BuckarooDataHelper::M2_ORDER_STATE_PENDING,
                $this->checkPaymentType->isPaymentInTransit($payment),
                $order->getStatus() === Order::STATE_CANCELED
            ], true)
        );

        if ($this->checkPaymentType->isBuckarooPayment($payment) &&
            (
                ($order->getStatus() === BuckarooDataHelper::M2_ORDER_STATE_PENDING
                    && $this->checkPaymentType->isPaymentInTransit($payment)
                )
                || $order->getStatus() === Order::STATE_CANCELED
            )
        ) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        return $proceed();
    }
}