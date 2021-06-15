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

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Framework\App\ObjectManager;

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

    protected $quoteRepository;
    protected $quoteFactory;

    /** @var QuoteRecreate */
    private $quoteRecreate;

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
    protected $checkoutSession;

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

    protected $_customerFactory;
    protected $_sessionFactory;

    /**
     * @var \Magento\Sales\Model\OrderIncrementIdChecker
     */
    private $orderIncrementIdChecker;

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
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        QuoteRecreate $quoteRecreate,
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
        \Buckaroo\Magento2\Model\SecondChanceFactory $secondChanceFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Api\GuestCartManagementInterface $guestCart,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\SessionFactory $sessionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Sales\Model\OrderIncrementIdChecker $orderIncrementIdChecker = null

    ) {
        parent::__construct($context);
        $this->helper             = $helper;
        $this->cart               = $cart;
        $this->order              = $order;
        $this->quote              = $quote;
        $this->quoteRepository    = $quoteRepository;
        $this->quoteFactory       = $quoteFactory;
        $this->transaction        = $transaction;
        $this->logger             = $logger;
        $this->orderSender        = $orderSender;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->quoteRecreate      = $quoteRecreate;

        $this->accountConfig = $configProviderFactory->get('account');

        $this->_curlClient            = $curl;
        $this->_configProviderAccount = $configProviderAccount;
        $this->_storeManager          = $storeManager;
        $this->checkoutSession        = $checkoutSession;
        $this->_encryptor             = $encryptor;
        $this->priceCurrency          = $priceCurrency;
        $this->jsonHelper             = $jsonHelper;
        $this->jsonResultFactory      = $jsonResultFactory;
        $this->messageManager         = $messageManager;
        $this->_orderFactory          = $orderFactory;
        $this->sequenceManager        = $sequenceManager;
        $this->eavConfig              = $eavConfig;
        $this->urlBuilder             = $urlBuilder;

        $this->productFactory  = $productFactory;
        $this->quoteManagement = $quoteManagement;
        $this->customerSession = $customerSession;

        $this->_customerFactory             = $customerFactory;
        $this->_sessionFactory              = $sessionFactory;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;

        $this->guestCart           = $guestCart;
        $this->secondChanceFactory = $secondChanceFactory;

        $this->orderIncrementIdChecker = $orderIncrementIdChecker ?: ObjectManager::getInstance()
            ->get(\Magento\Sales\Model\OrderIncrementIdChecker::class);
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
        $mode           = $this->_configProviderAccount->getActive();
        $storeId        = $this->_storeManager->getStore()->getId();
        $data           = $this->response;
        if ($buckaroo_second_chance = $data['token']) {
            $secondChance = $this->secondChanceFactory->create();
            $collection   = $secondChance->getCollection()
                ->addFieldToFilter(
                    'token',
                    array('eq' => $buckaroo_second_chance)
                );
            foreach ($collection as $item) {
                $order = $this->_orderFactory->create()->loadByIncrementId($item->getOrderId());
                if ($this->customerSession->isLoggedIn()) {
                    $this->customerSession->logout();
                }

                if($customerId = $order->getCustomerId()){
                    $customer = $this->_customerFactory->create()->load($customerId);
                    $sessionManager = $this->_sessionFactory->create();
                    $sessionManager->setCustomerAsLoggedIn($customer);
                }
                
                $this->quoteRecreate->recreate($order);
                $this->setAvailableIncrementId($item->getOrderId(), $item);

            }
        }
        return $this->_redirect('checkout');
    }

    private function setAvailableIncrementId($orderId, $item)
    {
        for ($i = 1; $i < 100; $i++) {
            $newOrderId = $orderId . '-' . $i;
            if (!$this->orderIncrementIdChecker->isIncrementIdUsed($newOrderId)) {
                $this->checkoutSession->getQuote()->setReservedOrderId($newOrderId);
                $this->checkoutSession->getQuote()->save();
                $item->setLastOrderId($newOrderId);
                $item->save();
                return true;
            }
        }
    }

}
