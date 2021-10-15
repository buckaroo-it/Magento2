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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class Idin extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    protected $order;

    /**
     * @var \Magento\Quote\Model\Quote $quote
     */
    protected $quote;

    /** @var TransactionInterface */
    private $transaction;

    /**
     * @var \Buckaroo\Magento2\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

    /**
     * @var \Magento\Checkout\Model\ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Buckaroo\Magento2\Model\OrderStatusFactory
     */
    protected $orderStatusFactory;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /** @var CheckoutSession */
    protected $_checkoutSession;

    /**
     * @var PriceCurrencyInterface
     */
    public $priceCurrency;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    /** @var \Magento\Framework\Message\ManagerInterface */
    public $messageManager;

    protected $sequenceManager;

    protected $eavConfig;

    protected $urlBuilder;

    protected $groupTransaction;

    private $formKey;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    protected $transactionBuilderFactory;
    protected $gateway;

    private $customerSession;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param \Buckaroo\Magento2\Helper\Data                           $helper
     * @param \Magento\Checkout\Model\Cart                        $cart
     * @param \Magento\Sales\Model\Order                          $order
     * @param \Magento\Quote\Model\Quote                          $quote
     * @param TransactionInterface        $transaction
     * @param Log                                                 $logger
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory          $configProviderFactory
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Buckaroo\Magento2\Model\OrderStatusFactory              $orderStatusFactory
     * @param \Magento\Framework\HTTP\Client\Curl              $curl
     * @param Account       $configProviderAccount
     * @param  \Magento\Store\Model\StoreManagerInterface       $storeManager
     * @param  CheckoutSession $checkoutSession
     * @param PriceCurrencyInterface    $priceCurrency
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        TransactionInterface $transaction,
        Log $logger,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Buckaroo\Magento2\Model\OrderStatusFactory $orderStatusFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\SalesSequence\Model\Manager $sequenceManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\UrlInterface $urlBuilder,
        PaymentGroupTransaction $groupTransaction,
        ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->transactionBuilderFactory = $transactionBuilderFactory;
        $this->gateway                   = $gateway;
        $this->configProviderAccount     = $configProviderAccount;

        $this->helper             = $helper;
        $this->cart               = $cart;
        $this->order              = $order;
        $this->quote              = $quote;
        $this->transaction        = $transaction;
        $this->logger             = $logger;
        $this->orderSender        = $orderSender;
        $this->orderStatusFactory = $orderStatusFactory;

        $this->accountConfig = $configProviderFactory->get('account');

        $this->storeManager     = $storeManager;
        $this->_checkoutSession  = $checkoutSession;
        $this->priceCurrency     = $priceCurrency;
        $this->jsonHelper        = $jsonHelper;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->messageManager    = $messageManager;
        $this->_orderFactory     = $orderFactory;
        $this->sequenceManager   = $sequenceManager;
        $this->eavConfig         = $eavConfig;
        $this->urlBuilder        = $urlBuilder;

        $this->groupTransaction = $groupTransaction;
        $this->formKey          = $formKey;
        $this->scopeConfig      = $scopeConfig;

        $this->customerSession = $customerSession;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();

        if (!isset($data['issuer']) || empty($data['issuer'])) {
            $res['error'] = 'Issuer not valid';
            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($res);
        }

        $services = [
            'Name'             => 'Idin',
            'Action'           => 'verify',
            'Version'          => 0,
            'RequestParameter' => [
                [
                    '_'    => trim($data['issuer']),
                    'Name' => 'issuerId',
                ],
            ],
        ];

        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

        $orderId = $this->helper->getOrderId();
        $order   = $this->order->load($orderId);

        $activeMode = $this->configProviderAccount->getIdin($this->storeManager->getStore());

        $transactionBuilder = $this->transactionBuilderFactory->get('datarequest');
        $transaction        = $transactionBuilder->setOrder($order)
            ->setServices($services)
            ->setAdditionalParameter('idin_cid', $this->customerSession->getCustomer()->getId())
            ->setMethod('DataRequest')
            ->build();

        $response = $this->gateway->setMode($activeMode)->authorize($transaction);

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($response[0]);
    }
}
