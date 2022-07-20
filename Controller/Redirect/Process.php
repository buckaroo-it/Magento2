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

namespace Buckaroo\Magento2\Controller\Redirect;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Magento\Framework\App\Request\Http as Http;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Service\Order as OrderService;

class Process extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var  \Magento\Customer\Model\Session
     */
    public $customerSession;
    protected $customerRepository;
    protected $_sessionFactory;

    protected $customerModel;
    protected $customerResourceFactory;

    protected $orderService;

    /**
     * @var EventManager
     */
    private $eventManager;

    private $quoteRecreate;

    /**
     * @var PushRequestInterface
     */
    private PushRequestInterface $pushRequst;

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
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Customer\Model\SessionFactory $sessionFactory,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\Customer\Model\ResourceModel\CustomerFactory $customerFactory,
        OrderService $orderService,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Buckaroo\Magento2\Service\Sales\Quote\Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory
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
        $this->checkoutSession    = $checkoutSession;
        $this->customerSession    = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->_sessionFactory    = $sessionFactory;

        $this->customerModel           = $customerModel;
        $this->customerResourceFactory = $customerFactory;

        $this->accountConfig = $configProviderFactory->get('account');

        $this->orderService           = $orderService;
        $this->eventManager           = $eventManager;
        $this->quoteRecreate          = $quoteRecreate;

        // @codingStandardsIgnoreStart
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
        $this->pushRequst = $requestPushFactory->create();
        // @codingStandardsIgnoreEnd
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__ . '|' . var_export($this->pushRequst->getOriginalRequest(), true));

        /**
         * Check if there is a valid response. If not, redirect to home.
         */
        if (count($this->pushRequst->getData()) === 0 || empty($this->pushRequst->getStatusCode())) {
            return $this->handleProcessedResponse('/');
        }

        if ($this->pushRequst->hasPostData('primary_service', 'IDIN')) {
            if ($this->setCustomerIDIN()) {
                $this->addSuccessMessage(__('Your iDIN verified succesfully!'));
            } else {
                $this->addErrorMessage(
                    __(
                        'Unfortunately iDIN not verified!'
                    )
                );
            }

            return $this->redirectToCheckout();
        }

        $statusCode = (int) $this->pushRequst->getStatusCode();

        $this->loadOrder();
        $this->helper->setRestoreQuoteLastOrder(false);

        if (!$this->order->getId()) {
            $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED');
        } else {
            $this->quote->load($this->order->getQuoteId());
        }

        $payment = $this->order->getPayment();

        if($payment) {
            $this->setPaymentOutOfTransit($payment);
        }

        if (!method_exists($payment->getMethodInstance(), 'canProcessPostData')) {
            return $this->handleProcessedResponse('/');
        }

        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->pushRequst)) {
            return $this->handleProcessedResponse('/');
        }

        $this->logger->addDebug(__METHOD__ . '|2|' . var_export($statusCode, true));

        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_paypal')
            && ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'))
        ) {
            $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER');
            $this->logger->addDebug(__METHOD__ . '|22|' . var_export($statusCode, true));
        }

        switch ($statusCode) {
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'):
                $debugInfo = [
                    $this->order->getStatus(),
                    $this->orderStatusFactory->get(
                        $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'),
                        $this->order
                    ),
                ];
                $this->logger->addDebug(__METHOD__ . '|3|' . var_export($debugInfo, true));

                if ($this->order->canInvoice()) {
                    $this->logger->addDebug(__METHOD__ . '|31|');
                    if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')) {
                        //do nothing - push will change a status
                        $this->logger->addDebug(__METHOD__ . '|32|');
                    } else {
                        $this->logger->addDebug(__METHOD__ . '|33|');
                        // Set the 'Pending payment status' here
                        $pendingStatus = $this->orderStatusFactory->get(
                            $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'),
                            $this->order
                        );
                        if ($pendingStatus) {
                            $this->logger->addDebug(__METHOD__ . '|34|' . var_export($pendingStatus, true));
                            $this->order->setStatus($pendingStatus);
                            $this->order->save();
                        }
                    }

                }

                $payment->getMethodInstance()->processCustomPostData($payment, $this->pushRequst->getData());

                /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
                $paymentMethod = $this->order->getPayment()->getMethodInstance();
                $store         = $this->order->getStore();

                // Send order confirmation mail if we're supposed to
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                if (!$this->order->getEmailSent()
                    && ($this->accountConfig->getOrderConfirmationEmail($store) === "1"
                        || $paymentMethod->getConfigData('order_email', $store) === "1"
                    )
                ) {
                    if (!($this->pushRequst->hasAdditionalInformation('initiated_by_magento', 1) &&
                        $this->pushRequst->hasPostData('primary_service', 'KlarnaKp') &&
                        $this->pushRequst->hasAdditionalInformation('service_action_from_magento', 'reserve') &&
                        !empty($this->pushRequst->getServiceKlarnakpReservationnumber())
                    )) {
                        if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')) {
                            $this->logger->addDebug(__METHOD__ . '|sendemail|');
                            $this->orderSender->send($this->order, true);
                        }
                    }
                }

                $pendingCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING');
                if (($statusCode == $pendingCode)
                    && !$this->hasPostData('brq_payment_method', 'sofortueberweisung')
                ) {
                    $this->addErrorMessage(
                        __(
                            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                            ' error persists, please choose a different payment method.'
                        )
                    );
                    $this->logger->addDebug(__METHOD__ . '|5|');

                    return $this->handleProcessedResponse('/');
                }

                $this->logger->addDebug(__METHOD__ . '|51|' . var_export([
                    $this->checkoutSession->getLastSuccessQuoteId(),
                    $this->checkoutSession->getLastQuoteId(),
                    $this->checkoutSession->getLastOrderId(),
                    $this->checkoutSession->getLastRealOrderId(),
                    $this->order->getQuoteId(),
                    $this->order->getId(),
                    $this->order->getIncrementId(),
                ], true));

                if (!$this->checkoutSession->getLastSuccessQuoteId() && $this->order->getQuoteId()) {
                    $this->logger->addDebug(__METHOD__ . '|52|');
                    $this->checkoutSession->setLastSuccessQuoteId($this->order->getQuoteId());
                }
                if (!$this->checkoutSession->getLastQuoteId() && $this->order->getQuoteId()) {
                    $this->logger->addDebug(__METHOD__ . '|53|');
                    $this->checkoutSession->setLastQuoteId($this->order->getQuoteId());
                }
                if (!$this->checkoutSession->getLastOrderId() && $this->order->getId()) {
                    $this->logger->addDebug(__METHOD__ . '|54|');
                    $this->checkoutSession->setLastOrderId($this->order->getId());
                }
                if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                    $this->logger->addDebug(__METHOD__ . '|55|');
                    $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                }
                $this->logger->addDebug(__METHOD__ . '|6|');
                // Redirect to success page
                return $this->redirectSuccess();
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'):
                return $this->handleFailed($statusCode);
                break;
                //no default
        }

        $this->logger->addDebug(__METHOD__ . '|9|');
        return $this->_response;
    }
    /**
     * Handle final response
     *
     * @param string $path
     * @param array $arguments
     *
     * @return ResponseInterface
     */
    public function handleProcessedResponse($path, $arguments = [])
    {
        $this->logger->addDebug(__METHOD__ . '|15|');
        return $this->_redirect($path, $arguments);
    }
    /**
     * Get order
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Add error message to be displayed to the user
     *
     * @param string $message
     *
     * @return void
     */
    public function addErrorMessage(string $message)
    {
        $this->messageManager->addErrorMessage($message);
    }
    /**
     * Add success message to be displayed to the user
     *
     * @param string $message
     *
     * @return void
     */
    public function addSuccessMessage(string $message)
    {
        $this->messageManager->addSuccessMessage($message);
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     *
     * @return void
     */
    protected function setPaymentOutOfTransit(OrderPaymentInterface $payment)
    {
        $payment
        ->setAdditionalInformation(AbstractMethod::BUCKAROO_PAYMENT_IN_TRANSIT, false)
        ->save();
    }
    protected function handleFailed($statusCode)
    {
        $this->logger->addDebug(__METHOD__ . '|7|');

        $this->eventManager->dispatch('buckaroo_process_handle_failed_before');

        if (!$this->getSkipHandleFailedRecreate()) {
            if (!$this->quoteRecreate->recreate($this->quote)) {
                $this->logging->addError('Could not recreate the quote.');
            }
        }

        /*
         * Something went wrong, so we're going to have to
         * 1) recreate the quote for the user
         * 2) cancel the order we had to create to even get here
         * 3) redirect back to the checkout page to offer the user feedback & the option to try again
         */

        // StatusCode specified error messages
        $statusCodeAddErrorMessage                                                                 = [];
        $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED')] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED')] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED')] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[
            $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER')
        ] = 'According to our system, you have canceled the payment. If this' .
            ' is not the case, please contact us.';

        $this->addErrorMessage(
            __(
                $statusCodeAddErrorMessage[$statusCode]
            )
        );

        //skip cancel order for PPE
        if (!empty($this->pushRequst->getAdditionalInformation('frompayperemail'))) {
            return $this->redirectFailure();
        }

        if (!$this->cancelOrder($statusCode)) {
            $this->logger->addError('Could not cancel the order.');
        }
        $this->logger->addDebug(__METHOD__ . '|8|');
        return $this->redirectFailure();
    }

    /**
     * @throws \Buckaroo\Magento2\Exception
     */
    private function loadOrder()
    {
        $brqOrderId = false;

        if (!empty($this->pushRequst->getInvoiceNumber())) {
            $brqOrderId = $this->pushRequst->getInvoiceNumber();
        }

        if (!empty($this->pushRequst->getOrderNumber())) {
            $brqOrderId = $this->pushRequst->getOrderNumber();
        }

        $this->order->loadByIncrementId($brqOrderId);

        if (!$this->order->getId()) {
            $this->logger->addDebug('Order could not be loaded by brq_invoicenumber or brq_ordernumber');
            $this->order = $this->getOrderByTransactionKey();
        }
    }

    /**
     * @return bool
     * @throws \Buckaroo\Magento2\Exception
     */
    private function getOrderByTransactionKey()
    {
        $trxId = '';

        if (!empty($this->pushRequst->getTransactions())) {
            $trxId = $this->pushRequst->getTransactions();
        }

        if (!empty($this->pushRequst->getDatarequest())) {
            $trxId = $this->pushRequst->getDatarequest();
        }

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \Buckaroo\Magento2\Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * If possible, cancel the order
     *
     * @param $statusCode
     *
     * @return bool
     */
    protected function cancelOrder($statusCode)
    {
        return $this->orderService->cancel($this->order, $statusCode);
    }

    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectSuccess()
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $this->eventManager->dispatch('buckaroo_process_redirect_success_before');

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        $this->addSuccessMessage(__('Your order has been placed successfully.'));

        $this->quote->setReservedOrderId(null);

        if (!empty($this->pushRequst->getPaymentMethod())
            &&
            ($this->pushRequst->getPaymentMethod() == 'applepay')
            &&
            !empty($this->pushRequst->getStatusCode())
            &&
            ($this->pushRequst->getStatusCode() == '190')
            &&
            !empty($this->pushRequst->getTest())
            &&
            ($this->pushRequst->getTest() == 'true')
        ) {
            $this->redirectSuccessApplePay();
        }

        $this->logger->addDebug(__METHOD__ . '|2|' . var_export($url, true));

        return $this->handleProcessedResponse($url);
    }

    protected function redirectSuccessApplePay()
    {
        $this->logger->addDebug(__METHOD__);

        $this->checkoutSession
            ->setLastQuoteId($this->order->getQuoteId())
            ->setLastSuccessQuoteId($this->order->getQuoteId())
            ->setLastOrderId($this->order->getId())
            ->setLastRealOrderId($this->order->getIncrementId())
            ->setLastOrderStatus($this->order->getStatus());
    }

    /**
     * Redirect to Failure url, which means we've got a problem
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectFailure()
    {
        $store = $this->order->getStore();
        $this->logger->addDebug('start redirectFailure');
        if ($this->accountConfig->getFailureRedirectToCheckout($store)) {
            $this->logger->addDebug('getFailureRedirectToCheckout');
            if (!$this->customerSession->isLoggedIn() && ($this->order->getCustomerId() > 0)) {
                $this->logger->addDebug('not isLoggedIn');
                $this->logger->addDebug('getCustomerId > 0');
                try {
                    $customer = $this->customerRepository->getById($this->order->getCustomerId());
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);

                    if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                        $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                        $this->logger->addDebug(__METHOD__ . '|setLastRealOrderId|');
                        if (!$this->getSkipHandleFailedRecreate()) {
                            $this->checkoutSession->restoreQuote();
                            $this->logger->addDebug(__METHOD__ . '|restoreQuote|');
                        }
                    }
                    $this->setSkipHandleFailedRecreate(false);
                } catch (\Exception $e) {
                    $this->logger->addError('Could not load customer');
                }
            }
            $this->logger->addDebug('ready for redirect');
            return $this->handleProcessedResponse('checkout', ['_fragment' => 'payment', '_query' => ['bk_e' => 1]]);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getFailureRedirect($store);

        return $this->handleProcessedResponse($url);
    }

    protected function redirectToCheckout()
    {
        $store = $this->order->getStore();
        $this->logger->addDebug('start redirectToCheckout');
        if (!$this->customerSession->isLoggedIn()) {
            $this->logger->addDebug('not isLoggedIn');
            if ($this->order->getCustomerId() > 0) {
                $this->logger->addDebug('getCustomerId > 0');
                try {
                    $customer = $this->customerRepository->getById($this->order->getCustomerId());
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);

                    if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                        $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                        $this->logger->addDebug(__METHOD__ . '|setLastRealOrderId|');
                        $this->checkoutSession->restoreQuote();
                        $this->logger->addDebug(__METHOD__ . '|restoreQuote|');
                    } elseif ($this->hasPostData('brq_primary_service', 'IDIN')) {
                        $this->checkoutSession->restoreQuote();
                    }

                } catch (\Exception $e) {
                    $this->logger->addError('Could not load customer');
                }
            }
        }
        $this->logger->addDebug('ready for redirect');
        return $this->handleProcessedResponse('checkout', ['_query' => ['bk_e' => 1]]);
    }

    private function setCustomerIDIN()
    {
        if (!empty($this->pushRequst->getServiceIdinConsumerbin())
            && !empty($this->pushRequst->getServiceIdinIseighteenorolder())
            && $this->pushRequst->getServiceIdinIseighteenorolder() == 'True'
        ) {
            $this->checkoutSession->setCustomerIDIN($this->pushRequst->getServiceIdinConsumerbin());
            $this->checkoutSession->setCustomerIDINIsEighteenOrOlder(true);
            if (!empty($this->pushRequst->getAdditionalInformation('idin_cid'))) {
                $customerNew  = $this->customerModel->load((int) $this->pushRequst->getAdditionalInformation('idin_cid'));
                $customerData = $customerNew->getDataModel();
                $customerData->setCustomAttribute('buckaroo_idin', $this->pushRequst->getServiceIdinConsumerbin());
                $customerData->setCustomAttribute('buckaroo_idin_iseighteenorolder', 1);
                $customerNew->updateData($customerData);
                $customerResource = $this->customerResourceFactory->create();
                $customerResource->saveAttribute($customerNew, 'buckaroo_idin');
                $customerResource->saveAttribute($customerNew, 'buckaroo_idin_iseighteenorolder');
            }
            return true;
        }
        return false;
    }

    public function getSkipHandleFailedRecreate()
    {
        return false;
    }

    public function setSkipHandleFailedRecreate($value)
    {
        return true;
    }
}
