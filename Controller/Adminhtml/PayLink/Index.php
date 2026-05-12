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

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Index extends Action implements HttpGetActionInterface
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var CommandManagerPoolInterface
     */
    private $commandManagerPool;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param Context                     $context
     * @param Http                        $request
     * @param OrderRepositoryInterface    $orderRepository
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param BuckarooLoggerInterface     $logger
     */
    public function __construct(
        Context $context,
        Http $request,
        OrderRepositoryInterface $orderRepository,
        CommandManagerPoolInterface $commandManagerPool,
        BuckarooLoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->commandManagerPool = $commandManagerPool;
        $this->logger = $logger;
    }

    /**
     * Check if the current user is allowed to generate PayLinks.
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Buckaroo_Magento2::paylink');
    }

    /**
     * Generate PayLink from Sales Order View Admin
     *
     * @throws Exception
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $orderId = $this->request->getParam('order_id');

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setUrl($this->_redirect->getRefererUrl());
        }

        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();
        $currentPayment = $payment->getMethod();
        $payment->setMethod('buckaroo_magento2_payperemail');
        $payment->save();
        $order->save();

        try {
            $commandExecutor = $this->commandManagerPool->get('buckaroo');

            $commandExecutor->executeByCode(
                'paylink',
                $payment,
                [
                    'amount' => $order->getGrandTotal()
                ]
            );
        } catch (NotFoundException|CommandException $exception) {
            $this->logger->addError(
                sprintf('[PayLink] Command error for order %s: %s', $orderId, $exception->getMessage())
            );
            $this->messageManager->addErrorMessage(__('Unable to generate PayLink. Please try again.'));
        } catch (Exception $e) {
            $this->logger->addError(sprintf('[PayLink] Unexpected error for order %s: %s', $orderId, $e->getMessage()));
            $this->messageManager->addErrorMessage(__('An unexpected error occurred. Please try again.'));
        } finally {
            $payment = $order->getPayment();
            $payment->setMethod($currentPayment);
            $payment->save();
            $order->save();
        }

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setUrl($this->_redirect->getRefererUrl());
    }
}
