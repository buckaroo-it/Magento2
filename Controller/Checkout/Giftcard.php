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
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curlClient;

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
        \Magento\Framework\HTTP\Client\Curl $curl,
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
        \Magento\Framework\Data\Form\FormKey $formKey
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

        $this->_curlClient            = $curl;
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
        // $this->response = $this->getRequest()->getParams();
        $data = $this->getRequest()->getParams();
        $this->logger->addDebug(var_export($data, true));

        $currency = $this->_storeManager->getStore()->getCurrentCurrencyCode();
        $orderId = $this->helper->getOrderId();

        /*if(isset($data['refund'])){
            $transactionKey = $data['refund'];
            $amount_value = preg_replace("/([^0-9\\.,])/i", "", $data['amount']);
            $postArray = array(
                "Currency" => $currency,
                "AmountCredit" => $amount_value,
                "Invoice" => $orderId,
                "OriginalTransactionKey" => $transactionKey,
                "Services" => array(
                    "ServiceList" => array(
                        array(
                            "Action" => "Refund",
                            "Name" => $data['card'],
                            "Version" => 1,
                        )
                    )
                )
            );

            $response = $this->sendResponse($postArray);

            $res['status'] = $response['Status']['Code']['Code'];

            if($response['Status']['Code']['Code']=='190'){
                $groupTransaction = $this->groupTransaction->getGroupTransactionByTrxId($transactionKey);
                foreach ($groupTransaction as $item) {
                    if (!empty(floatval($item['refunded_amount']))) {
                        $item['refunded_amount'] += $amount_value;
                    } else {
                        $item['refunded_amount'] = $amount_value;
                    }
                   $this->groupTransaction->updateGroupTransaction($item->_data);
                }

                $alreadyPaid = $this->getAlreadyPaid($orderId) - $amount_value;
                $this->setAlreadyPaid($orderId, $alreadyPaid);

                $res['message'] = __("Your refund successfully.");
            }else{
                $res['error'] = isset($response['Status']['SubCode']['Description']) ? $response['Status']['SubCode']['Description'] : $response['RequestErrors']['ServiceErrors'][0]['ErrorMessage'];
            }

            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($res);     
        }*/

        if (!isset($data['card']) || empty($data['card']) || !isset($data['cardNumber']) || empty($data['cardNumber']) || !isset($data['pin']) || empty($data['pin'])) {
            $res['error'] = 'Card number or pin not valid';
            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($res);
        }

        $card = $data['card'];
        // $returnUrl = $this->_storeManager->getStore()->getUrl($this->_configProviderAccount->getSuccessRedirect());
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

        $postArray = array(
            "Currency" => $currency,
            "AmountDebit" => $grandTotal,
            "Invoice" => $orderId,
            "ReturnURL" => $returnUrl,
            "ReturnURLCancel" => $returnUrl,
            "ReturnURLError" => $returnUrl,
            "ReturnURLReject" => $returnUrl,
            "PushURL" => $pushUrl,
            "Services" => array(
                "ServiceList" => array(
                    array(
                        "Action" => "Pay",
                        "Name" => $card,
                        "Parameters" => array(
                            array(
                                "Name" => $parameters['number'],
                                "Value" => trim(preg_replace('/([\s-]+)/', '', $data['cardNumber']))
                            ),array(
                                "Name" => $parameters['pin'],
                                "Value" => trim($data['pin'])
                            )
                        )
                    )
                )
            )
        );

        if($originalTransactionKey = $this->getOriginalTransactionKey($orderId)){
            $postArray['Services']['ServiceList'][0]['Action'] = 'PayRemainder';
            $postArray['OriginalTransactionKey'] = $originalTransactionKey;
        }

        $response = $this->sendResponse($postArray);

        $res['status'] = $response['Status']['Code']['Code'];
        $orderId = $response['Invoice'];

        $this->logger->addDebug(__METHOD__.'|2|');
        $this->logger->addDebug(var_export($response, true));

        if($response['Status']['Code']['Code']=='190'){
            
            $this->groupTransaction->saveGroupTransaction($response);

            $res['RemainderAmount'] = $response['RequiredAction']['PayRemainderDetails']['RemainderAmount'] ?? null;
            $alreadyPaid = $this->getAlreadyPaid($orderId) + $response['AmountDebit'];
            
            $res['PayRemainingAmountButton'] = '';
            if($res['RemainderAmount'] > 0){
                $this->setOriginalTransactionKey($orderId, $response['RequiredAction']['PayRemainderDetails']['GroupTransaction']);
                $message = __('A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4', $response['Currency'], $response['AmountDebit'],$res['RemainderAmount'],$response['RequiredAction']['PayRemainderDetails']['Currency']);
                $res['PayRemainingAmountButton'] = __('Pay remaining amount: %1 %2', $res['RemainderAmount'],$response['RequiredAction']['PayRemainderDetails']['Currency']);
            }else{
                $message = __("Your paid successfully. Please finish your order");
            }
            $this->setAlreadyPaid($orderId, $alreadyPaid);
            $res['alreadyPaid'] = $alreadyPaid;
            $res['message'] = $message;

        }else{
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

    private function sendResponse($data){
        $secretKey =  $this->_encryptor->decrypt($this->_configProviderAccount->getSecretKey());
        $websiteKey =  $this->_encryptor->decrypt($this->_configProviderAccount->getMerchantKey());

        $url = ($this->scopeConfig->getValue(
                'payment/buckaroo_magento2_giftcards/active',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) == 2) ? 'checkout.buckaroo.nl': 'testcheckout.buckaroo.nl';
        $uri        = 'https://'.$url.'/json/Transaction';
        $uri2       = strtolower(rawurlencode($url.'/json/Transaction'));

        $timeStamp = time();
        $httpMethod = 'POST';
        $nonce      = $this->stringRandom();

        $json = json_encode($data, JSON_PRETTY_PRINT);
        $md5 = md5($json, true);
        $encodedContent = base64_encode($md5);

        $rawData = $websiteKey . $httpMethod . $uri2 . $timeStamp . $nonce . $encodedContent;
        $hash = hash_hmac('sha256', $rawData, $secretKey, true);
        $hmac = base64_encode($hash);

        $hmac_full = $websiteKey . ':' . $hmac . ':' . $nonce . ':' . $timeStamp;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Magento2');
        curl_setopt($curl, CURLOPT_URL, $uri);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $httpMethod);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        //ZAK
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: hmac ' . $hmac_full,
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($curl);

        $curlInfo = curl_getinfo($curl);
        return json_decode($result, true);
    }

    private function stringRandom($length = 16)
    {
        $chars = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
        $str = "";

        for ($i=0; $i < $length; $i++)
        {
            $key = array_rand($chars);
            $str .= $chars[$key];
        }

        return $str;
    }

    private function getAlreadyPaid($orderId = false)
    {
        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();
        return isset($alreadyPaid[$orderId]) ? $alreadyPaid[$orderId] : false;
    }

    private function setAlreadyPaid($orderId, $amount)
    {
        if($orderId){
            $this->_checkoutSession->getQuote()->setBaseBuckarooAlreadyPaid($amount);
            $this->_checkoutSession->getQuote()->setBuckarooAlreadyPaid($this->priceCurrency->convert($amount, $this->quote->getStore()));
        }

        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();
        $alreadyPaid[$orderId] = $amount;
        $this->_checkoutSession->setBuckarooAlreadyPaid($alreadyPaid);
    }

    private function getOriginalTransactionKey($orderId)
    {
        $originalTransactionKey = $this->_checkoutSession->getOriginalTransactionKey();
        return isset($originalTransactionKey[$orderId]) ? $originalTransactionKey[$orderId] : false;
    }

    private function setOriginalTransactionKey($orderId, $transactionKey)
    {
        $originalTransactionKey = $this->_checkoutSession->getOriginalTransactionKey();
        $originalTransactionKey[$orderId] = $transactionKey;
        $this->_checkoutSession->setOriginalTransactionKey($originalTransactionKey);
    }

    private function getOrder()
    {
        if ($this->_checkoutSession->getLastRealOrderId()) {
            $order = $this->_orderFactory->create()->loadByIncrementId($this->_checkoutSession->getLastRealOrderId());
            return $order;
        }
        return false;
    }
}
