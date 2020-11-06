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

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class SecondChance extends \Magento\Framework\App\Action\Action
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

    protected $secondChanceFactory;

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
        \Buckaroo\Magento2\Model\SecondChanceFactory $secondChanceFactory
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

        $this->secondChanceFactory = $secondChanceFactory;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->response = $this->getRequest()->getParams();
        $mode =  $this->_configProviderAccount->getActive();
        $storeId = $this->_storeManager->getStore()->getId();
        $data = $this->response;
        if($buckaroo_second_chance = $data['token']){
            $secondChance = $this->secondChanceFactory->create();
            $collection   = $secondChance->getCollection()
                    ->addFieldToFilter(
                        'token',
                        array('eq' => $buckaroo_second_chance)
                    );
            foreach ($collection as $item) {
                $order = $this->_orderFactory->create()->loadByIncrementId($item->getOrderId());
                $this->quote->load($order->getQuoteId());
                
                $this->_checkoutSession
                    ->setLastQuoteId($order->getQuoteId())
                    ->setLastSuccessQuoteId($order->getQuoteId())
                    ->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());

                $this->_checkoutSession->restoreQuote();
            }
        }
        return $this->_redirect('checkout');
    }
}
