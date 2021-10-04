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

use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\Data\TransactionInterface;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class Giftcard extends \Magento\Framework\App\Action\Action
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

    /** @var Encryptor $encryptor */
    private $encryptor;

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

    protected $client;

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
     * @param Encryptor     $encryptor
     * @param PriceCurrencyInterface    $priceCurrency
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        TransactionInterface $transaction,
        Log $logger,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Buckaroo\Magento2\Model\OrderStatusFactory $orderStatusFactory,
        Account $configProviderAccount,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        Encryptor $encryptor,
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
        \Buckaroo\Magento2\Gateway\Http\Client\Json $client
    ) {
        parent::__construct($context);
        $this->helper             = $helper;
        $this->cart               = $cart;
        $this->order              = $order;
        $this->quote              = $quote;
        $this->transaction        = $transaction;
        $this->logger             = $logger;
        $this->orderSender        = $orderSender;
        $this->orderStatusFactory = $orderStatusFactory;

        $this->accountConfig = $configProviderFactory->get('account');

        $this->_configProviderAccount = $configProviderAccount;
        $this->_storeManager          = $storeManager;
        $this->_checkoutSession       = $checkoutSession;
        $this->_encryptor             = $encryptor;
        $this->priceCurrency          = $priceCurrency;
        $this->jsonHelper = $jsonHelper;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->messageManager = $messageManager;
        $this->_orderFactory = $orderFactory;
        $this->sequenceManager = $sequenceManager;
        $this->eavConfig = $eavConfig;
        $this->urlBuilder = $urlBuilder;

        $this->groupTransaction = $groupTransaction;
        $this->formKey          = $formKey;
        $this->scopeConfig = $scopeConfig;
        $this->client = $client;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__.'|1|');
        $data = $this->getRequest()->getParams();
        $this->logger->addDebug(var_export($data, true));

        $currency = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $orderId = $this->helper->getOrderId();

        if (!isset($data['card'])
            || empty($data['card'])
            || !isset($data['cardNumber'])
            || empty($data['cardNumber'])
            || !isset($data['pin'])
            || empty($data['pin'])
        ) {
            $res['error'] = __('Card number or pin not valid');
            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($res);
        }

        $card = $data['card'];
        $returnUrl = $this->urlBuilder->setScope($this->_storeManager->getStore()->getStoreId());
        $returnUrl = $returnUrl->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->formKey->getFormKey();

        $pushUrl = $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push');

        switch ($card) {
            case 'fashioncheque':
                $parameters = [
                    'number' => 'FashionChequeCardNumber',
                    'pin' => 'FashionChequePin',
                ];
                break;
            case 'tcs':
                $parameters = [
                    'number' => 'TCSCardnumber',
                    'pin' => 'TCSValidationCode',
                ];
                break;
            default:
                if (stristr($card, 'customgiftcard') === false) {
                    $parameters = [
                        'number' => 'IntersolveCardnumber',
                        'pin' => 'IntersolvePin',
                    ];
                } else {
                    $parameters = [
                        'number' => 'Cardnumber',
                        'pin' => 'Pin',
                    ];
                }
        }

        $cartTotals = $this->_checkoutSession->getQuote()->getTotals();
        $grand_total = $cartTotals['grand_total']->getData();
        $grandTotal =  $grand_total['value'];

        if ($alreadyPaid = $this->helper->getBuckarooAlreadyPaid($orderId)) {
            $payRemainder = $grandTotal - $alreadyPaid;
            $this->logger->addDebug(__METHOD__ . '|11|' . var_export([$orderId, $payRemainder], true));
            $grandTotal = $payRemainder;
        }

        $postArray = [
            "Currency" => $currency,
            "AmountDebit" => $grandTotal,
            "Invoice" => $orderId,
            "ReturnURL" => $returnUrl,
            "ReturnURLCancel" => $returnUrl,
            "ReturnURLError" => $returnUrl,
            "ReturnURLReject" => $returnUrl,
            "PushURL" => $pushUrl,
            "Services" => [
                "ServiceList" => [
                    [
                        "Action" => "Pay",
                        "Name" => $card,
                        "Parameters" => [
                            [
                                "Name" => $parameters['number'],
                                "Value" => trim(preg_replace('/([\s-]+)/', '', $data['cardNumber']))
                            ], [
                                "Name" => $parameters['pin'],
                                "Value" => trim($data['pin'])
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if ($originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($orderId)) {
            $postArray['Services']['ServiceList'][0]['Action'] = 'PayRemainder';
            $postArray['OriginalTransactionKey'] = $originalTransactionKey;
        }

        $response = $this->sendResponse($postArray);

        $res['status'] = $response['Status']['Code']['Code'];
        $orderId = $response['Invoice'];

        $this->logger->addDebug(__METHOD__.'|2|');
        $this->logger->addDebug(var_export($response, true));

        if ($response['Status']['Code']['Code']=='190') {
            
            $this->groupTransaction->saveGroupTransaction($response);

            $res['RemainderAmount'] = $response['RequiredAction']['PayRemainderDetails']['RemainderAmount'] ?? null;
            $alreadyPaid = $this->groupTransaction->getGroupTransactionAmount($orderId);
            
            $res['PayRemainingAmountButton'] = '';
            $t = 'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4';
            if ($res['RemainderAmount'] > 0) {
                $this->setOriginalTransactionKey(
                    $orderId,
                    $response['RequiredAction']['PayRemainderDetails']['GroupTransaction']
                );

                $message = __(
                    $t,
                    $response['Currency'],
                    $response['AmountDebit'],
                    $res['RemainderAmount'],
                    $response['RequiredAction']['PayRemainderDetails']['Currency']
                );
                $res['PayRemainingAmountButton'] = __(
                    'Pay remaining amount: %1 %2',
                    $res['RemainderAmount'],
                    $response['RequiredAction']['PayRemainderDetails']['Currency']
                );
            } else {
                $message = __("Your paid successfully. Please finish your order");
            }
            $this->setAlreadyPaid($orderId, $alreadyPaid);
            $res['alreadyPaid'] = $alreadyPaid;
            $res['message'] = $message;

        } else {
            $res['error'] = isset($response['Status']['SubCode']['Description']) ?
                $response['Status']['SubCode']['Description'] :
                (
                    isset($response['RequestErrors']['ServiceErrors'][0]['ErrorMessage']) ?
                            $response['RequestErrors']['ServiceErrors'][0]['ErrorMessage'] :
                            (
                                isset($response['Status']['Code']['Description']) ?
                                    $response['Status']['Code']['Description'] :
                                    ''
                            )
                )
            ;
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($res);
    }

    private function sendResponse($data)
    {
        $active = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_giftcards/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $mode = ($active == \Buckaroo\Magento2\Helper\Data::MODE_LIVE) ?
            \Buckaroo\Magento2\Helper\Data::MODE_LIVE : \Buckaroo\Magento2\Helper\Data::MODE_TEST;

        $this->client->setSecretKey($this->_encryptor->decrypt($this->_configProviderAccount->getSecretKey()));
        $this->client->setWebsiteKey($this->_encryptor->decrypt($this->_configProviderAccount->getMerchantKey()));

        return $this->client->doRequest($data, $mode);
    }

    private function setAlreadyPaid($orderId, $amount)
    {
        if ($orderId) {
            $this->_checkoutSession->getQuote()->setBaseBuckarooAlreadyPaid($amount);
            $this->_checkoutSession->getQuote()->setBuckarooAlreadyPaid(
                $this->priceCurrency->convert($amount, $this->quote->getStore())
            );
        }

        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();
        $alreadyPaid[$orderId] = $amount;
        $this->_checkoutSession->setBuckarooAlreadyPaid($alreadyPaid);
    }

    private function setOriginalTransactionKey($orderId, $transactionKey)
    {
        $originalTransactionKey = $this->_checkoutSession->getOriginalTransactionKey();
        $originalTransactionKey[$orderId] = $transactionKey;
        $this->_checkoutSession->setOriginalTransactionKey($originalTransactionKey);
    }
}
