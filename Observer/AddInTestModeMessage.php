<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class AddInTestModeMessage implements ObserverInterface
{
    public const PAYMENT_IN_TEST_MODE = 'buckaroo_payment_in_test_mode';

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @param ManagerInterface         $messageManager
     * @param RequestInterface         $request
     * @param OrderRepositoryInterface $orderRepository
     */
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
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws NotFoundException
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
     *
     * @throws NotFoundException
     *
     * @return bool
     */
    protected function isPaymentInTestMode(): bool
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
    protected function getOrder(): ?OrderInterface
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
