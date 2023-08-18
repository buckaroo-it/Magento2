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

use Buckaroo\Magento2\Helper\Data as BuckarooDataHelper;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Checkout\Controller\Onepage\Success as ControllerOnePageSuccess;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Model\Order;

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
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var CheckPaymentType
     */
    protected CheckPaymentType $checkPaymentType;

    /**
     * @param Context $context
     * @param BuckarooLoggerInterface $logger
     * @param CheckPaymentType $checkPaymentType
     */
    public function __construct(
        Context $context,
        BuckarooLoggerInterface $logger,
        CheckPaymentType $checkPaymentType
    ) {
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
        $this->logger = $logger;
        $this->checkPaymentType = $checkPaymentType;
    }

    /**
     * If the user visits the payment complete page when doing a payment or when the order is canceled redirect to cart
     *
     * @param ControllerOnePageSuccess $checkoutSuccess
     * @param callable $proceed
     * @return Redirect|Page
     */
    public function aroundExecute(ControllerOnePageSuccess $checkoutSuccess, callable $proceed)
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