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
namespace Buckaroo\Magento2\Controller\Adminhtml\PayLink;

use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;
    /**
     * @var Http
     */
    protected Http $request;
    /**
     * @var OrderInterface
     */
    private OrderInterface $order;
    /**
     * @var CommandManagerPoolInterface
     */
    private CommandManagerPoolInterface $commandManagerPool;
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Http $request
     * @param OrderInterface $order
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ManagerInterface $messageManager
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Http $request,
        OrderInterface $order,
        CommandManagerPoolInterface $commandManagerPool,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);
        $this->request                     = $request;
        $this->resultPageFactory           = $resultPageFactory;
        $this->order                       = $order;
        $this->commandManagerPool          = $commandManagerPool;
        $this->messageManager              = $messageManager;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Generate PayLink from Sales Order View Admin
     *
     * @throws NotFoundException
     * @throws CommandException
     * @throws \Exception
     */
    public function execute()
    {
        $orderId = $this->request->getParam('order_id');
        $order    = $this->order->load($orderId);

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

            $commandExecutor->executeByCode(
                'paylink',
                $payment,
                [
                    'amount' => $order->getGrandTotal()
                ]
            );
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
