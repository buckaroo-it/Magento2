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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AddInTestModeMessage implements ObserverInterface
{
    public const PAYMENT_IN_TEST_MODE = 'buckaroo_payment_in_test_mode';

    protected ManagerInterface $messageManager;

    protected RequestInterface $request;

    protected OrderRepositoryInterface $orderRepository;

    public function __construct(
        ManagerInterface $messageManager,
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->messageManager = $messageManager;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
    }
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer)
    {
        if ($this->isPaymentInTestMode()) {
            $this->messageManager->addWarningMessage(
                __('The payment for this order was made in test mode')
            );
        }
    }

    /**
     * Check to see if the payment for this order is in test mode
     */
    protected function isPaymentInTestMode()
    {
        $order = $this->getOrder();

        if (!($order instanceof OrderInterface)) {
            throw new NotFoundException(__('Order was not find by order ID'));
        }

        return $order->getPayment() !== null &&
            $order->getPayment()->getAdditionalInformation(self::PAYMENT_IN_TEST_MODE) === true;
    }

    /**
     * Get order by request order id
     *
     * @return OrderInterface|null
     */
    protected function getOrder()
    {
        $orderId = $this->request->getParam('order_id');
        if ($orderId === null || !is_scalar($orderId)) {
            return null;
        }
        return $this->orderRepository->get(
            (int)$orderId
        );
    }
}
