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
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class SaveOrder extends Common
{
    protected $quoteManagement;
    protected $customer;
    private $objectFactory;
    protected $registry = null;
    protected $order;
    protected $checkoutSession;
    protected $accountConfig;

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
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\Session $customer,
        \Magento\Framework\DataObjectFactory $objectFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
    ) {
        parent::__construct($context, $resultPageFactory, $inlineParser, $resultJsonFactory, $logger);

        $this->quoteManagement = $quoteManagement;
        $this->customer = $customer;
        $this->objectFactory = $objectFactory;
        $this->registry = $registry;
        $this->order = $order;
        $this->checkoutSession    = $checkoutSession;
        $this->accountConfig = $configProviderFactory->get('account');
    }

    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();
        $errorMessage = false;
        $data = [];
        $shippingMethodsResult = [];

        if ($isPost) {
            if (
                ($payment = $this->getRequest()->getParam('payment'))
                &&
                ($extra = $this->getRequest()->getParam('extra'))
            ) {
                $this->logger->addDebug(__METHOD__.'|1|');
                $this->logger->addDebug(var_export($payment, true));
                $this->logger->addDebug(var_export($extra, true));

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
                $quote = $checkoutSession->getQuote();

                if (!$this->setShippingAddress($quote, $payment['shippingContact'])) {
                    return $this->commonResponse(false, true);
                }
                if (!$this->setBillingAddress($quote, $payment['billingContact'])) {
                    return $this->commonResponse(false, true);
                }

                $this->logger->addDebug(var_export($quote->getShippingAddress()->getShippingMethod(), true));

                if ($this->customer->getCustomer() && $this->customer->getCustomer()->getId()) {
                } else {
                    $quote->setCheckoutMethod('guest')
                        ->setCustomerId(null)
                        ->setCustomerEmail($quote->getShippingAddress()->getEmail())
                        ->setCustomerIsGuest(true)
                        ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
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
                        if (
                            !empty($data->Status->Code->Code)
                            &&
                            ($data->Status->Code->Code == '190')
                            &&
                            !empty($data->Order)
                        ) {
                            $this->order->loadByIncrementId($data->Order);

                            if ($this->order->getId()) {
                                $this->checkoutSession
                                    ->setLastQuoteId($this->order->getQuoteId())
                                    ->setLastSuccessQuoteId($this->order->getQuoteId())
                                    ->setLastOrderId($this->order->getId())
                                    ->setLastRealOrderId($this->order->getIncrementId())
                                    ->setLastOrderStatus($this->order->getStatus());

                                $store = $this->order->getStore();
                                $url = $this->accountConfig->getSuccessRedirect($store);
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
