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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Model\Method\Applepay;
class SaveOrder extends Common
{
    protected $quoteManagement;
    protected $customer;
    private $objectFactory;
    protected $registry = null;
    protected $order;
    protected $checkoutSession;
    protected $accountConfig;
    private $configAccount;

    /**
     * @param Context     $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Translate\Inline\ParserInterface $inlineParser,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Log $logger,
        \Magento\Checkout\Model\Cart $cart,
        Account $configAccount,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\Session $customer,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        CustomerSession $customerSession = null
    ) {
        parent::__construct(
            $context,
            $resultPageFactory,
            $inlineParser,
            $resultJsonFactory,
            $logger,
            $cart,
            $totalsCollector,
            $converter,
            $customerSession
        );
        $this->configAccount               = $configAccount;
        $this->quoteManagement = $quoteManagement;
        $this->customer = $customer;
        $this->objectFactory = $objectFactory;
        $this->registry = $registry;
        $this->order = $order;
        $this->checkoutSession    = $checkoutSession;
        $this->accountConfig = $configProviderFactory->get('account');
    }
    //phpcs:ignore:Generic.Metrics.NestingLevel
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();
        $errorMessage = false;
        $data = [];
        $shippingMethodsResult = [];

        if ($isPost) {
            if (($payment = $this->getRequest()->getParam('payment'))
                &&
                ($extra = $this->getRequest()->getParam('extra'))
            ) {
                $this->logger->addDebug(__METHOD__.'|1|');
                $this->logger->addDebug(var_export($payment, true));
                $this->logger->addDebug(var_export($extra, true));

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get(\Magento\Checkout\Model\Session::class);
                $quote = $checkoutSession->getQuote();

                if (!$quote->getIsVirtual() && !$this->setShippingAddress($quote, $payment['shippingContact'])) {
                    return $this->commonResponse(false, true);
                }
                if (!$this->setBillingAddress($quote, $payment['billingContact'])) {
                    return $this->commonResponse(false, true);
                }

                $this->logger->addDebug(__METHOD__.'|2|');

                $emailAddress = $quote->getShippingAddress()->getEmail();

                if ($quote->getIsVirtual()) {
                    $emailAddress =  isset($payment['shippingContact']['emailAddress']) ? $payment['shippingContact']['emailAddress']: null;
                }

                if (!($this->customer->getCustomer() && $this->customer->getCustomer()->getId())) {
                    $quote->setCheckoutMethod('guest')
                        ->setCustomerId(null)
                        ->setCustomerEmail($emailAddress)
                        ->setCustomerIsGuest(true)
                        ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
                }

                $payment = $quote->getPayment();
                $payment->setMethod(Applepay::PAYMENT_METHOD_CODE);

                $invoiceHandlingConfig = $this->configAccount->getInvoiceHandling($this->order->getStore());

                if ($invoiceHandlingConfig == InvoiceHandlingOptions::SHIPMENT) {
                    $payment->setAdditionalInformation(InvoiceHandlingOptions::INVOICE_HANDLING, $invoiceHandlingConfig);
                    $payment->save();
                    $quote->setPayment($payment);
                }
                $quote->collectTotals()->save();

                $obj = $this->objectFactory->create();
                $obj->setData($extra);
                $quote->getPayment()->getMethodInstance()->assignData($obj);

                $order = $this->quoteManagement->submit($quote);

                $data = [];
                if ($this->registry && $this->registry->registry('buckaroo_response')) {
                    $data = $this->registry->registry('buckaroo_response')[0];
                    $this->logger->addDebug(__METHOD__.'|4|'.var_export($data, true));
                    if (!empty($data->RequiredAction->RedirectURL)) {
                        //test mode
                        $this->logger->addDebug(__METHOD__.'|5|');
                        $data = [
                           'RequiredAction' => $data->RequiredAction
                        ];
                    } else {
                        //live mode
                        $this->logger->addDebug(__METHOD__.'|6|');
                        if (!empty($data->Status->Code->Code)
                            &&
                            ($data->Status->Code->Code == '190')
                            &&
                            !empty($data->Order)
                        ) {
                            $this->order->loadByIncrementId($data->Order);

                            if ($this->order->getId()) {
                                $this->checkoutSession
                                    ->setLastOrderId($this->order->getId())
                                    ->setLastQuoteId($this->order->getQuoteId())
                                    ->setLastSuccessQuoteId($this->order->getQuoteId())
                                    ->setLastRealOrderId($this->order->getIncrementId())
                                    ->setLastOrderStatus($this->order->getStatus());

                                $store = $this->order->getStore();
                                $shortUrl = $this->accountConfig->getSuccessRedirect($store);
                                $url = $store->getBaseUrl() . '/' .$shortUrl ;
                                $this->logger->addDebug(__METHOD__.'|7|'.var_export($url, true));
                                $data = [
                                    'RequiredAction' => [
                                        'RedirectURL' => $url
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }
}

