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

namespace Buckaroo\Magento2\Controller\Adminhtml\PayLink;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Index extends Action implements HttpGetActionInterface
{
    /**
     * @var Http
     */
    protected Http $request;

    /**
     * @var CommandManagerPoolInterface
     */
    private CommandManagerPoolInterface $commandManagerPool;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @param Context $context
     * @param Http $request
     * @param OrderRepositoryInterface $orderRepository
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        Context $context,
        Http $request,
        OrderRepositoryInterface $orderRepository,
        CommandManagerPoolInterface $commandManagerPool
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->commandManagerPool = $commandManagerPool;
    }

    /**
     * Generate PayLink from Sales Order View Admin
     *
     * @return ResultInterface
     * @throws \Exception
     */
    public function execute(): ResultInterface
    {
        $orderId = $this->request->getParam('order_id');
        $order = $this->orderRepository->get($orderId);

        if (!$orderId) {
            $this->messageManager->addErrorMessage('Order not found!');
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setUrl($this->_redirect->getRefererUrl());
        }

        $payment = $order->getPayment();
        $currentPayment = $payment->getMethod();
        $payment->setMethod('buckaroo_magento2_payperemail');
        $payment->save();
        $order->save();

        try {
            $commandExecutor = $this->commandManagerPool->get('buckaroo');

            $result = $commandExecutor->executeByCode(
                'paylink',
                $payment,
                [
                    'amount' => $order->getGrandTotal()
                ]
            );
        } catch (NotFoundException|CommandException $exception) {
                $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        $payment = $order->getPayment();
        $payment->setMethod($currentPayment);
        $payment->save();
        $order->save();

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setUrl($this->_redirect->getRefererUrl());
    }
}
