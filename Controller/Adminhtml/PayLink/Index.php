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

use Buckaroo\Magento2\Gateway\Http\TransactionBuilder\Order;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use \Magento\Framework\Controller\ResultFactory;

class Index extends \Magento\Backend\App\Action
{
    protected $resultPageFactory;

    protected $request;

    private $order;

    protected $transactionBuilderFactory;

    /** @var Factory */
    protected $configProviderMethodFactory;

    /** @var \Buckaroo\Magento2\Gateway\GatewayInterface */
    protected $gateway;

    protected $_messageManager;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory,
        Factory $configProviderMethodFactory,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);
        $this->request                     = $request;
        $this->resultPageFactory           = $resultPageFactory;
        $this->order                       = $order;
        $this->transactionBuilderFactory   = $transactionBuilderFactory;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->gateway                     = $gateway;
        $this->_messageManager             = $messageManager;
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        $order_id = $this->request->getParam('order_id');
        $order    = $this->order->load($order_id);

        if (!$order_id) {
            $this->_messageManager->addErrorMessage('Order not found!');
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setUrl($this->_redirect->getRefererUrl());
        }

        $config = $this->configProviderMethodFactory->get('paylink');

        $payment = $order->getPayment();
        $store = $payment->getMethodInstance()->getStore();
        $services = [
            'Name'             => 'payperemail',
            'Action'           => 'PaymentInvitation',
            'Version'          => 1,
            'RequestParameter' => [
                [
                    '_'    => 'true',
                    'Name' => 'MerchantSendsEmail',
                ],
                [
                    '_'    => $order->getCustomerGender() ?? 1,
                    'Name' => 'CustomerGender',
                ],
                [
                    '_'    => $order->getCustomerEmail(),
                    'Name' => 'CustomerEmail',
                ],
                [
                    '_'    => $order->getCustomerFirstname(),
                    'Name' => 'CustomerFirstName',
                ],
                [
                    '_'    => $order->getCustomerLastname(),
                    'Name' => 'CustomerLastName',
                ],
                [
                    '_'    => $config->getPaymentMethod($store),
                    'Name' => 'PaymentMethodsAllowed',
                ],
            ]
        ];

        $currentPayment = $payment->getMethod();
        $payment->setMethod('buckaroo_magento2_payperemail');
        $payment->save();
        $order->save();

        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $transactionBuilder->setOrder($order)
            ->setServices($services)
            ->setAdditionalParameter('fromPayLink', 1)
            ->setAdditionalParameter('fromPayPerEmail', 1)
            ->setMethod('TransactionRequest');

        try {
            $transaction = $transactionBuilder->build();
            $this->gateway->setMode($config->getActive($order->getStore()));

            $response = $this->gateway->authorize($transaction);
            if (is_array($response[0]->Services->Service->ResponseParameter)) {
                foreach ($response[0]->Services->Service->ResponseParameter as $parameter) {
                    if ($parameter->Name == 'PayLink') {
                        $payLink = $parameter->_;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->_messageManager->addErrorMessage($e->getMessage());
        }

        if (empty($payLink)) {
            $this->_messageManager->addErrorMessage('Error creating PayLink');
        } else {
            $this->_messageManager->addSuccess(
                __(
                    'Your PayLink <a href="%1">%1</a>',
                    $payLink
                )
            );
        }

        $payment = $order->getPayment();
        $payment->setMethod($currentPayment);
        $payment->save();
        $order->save();

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setUrl($this->_redirect->getRefererUrl());
    }
}
