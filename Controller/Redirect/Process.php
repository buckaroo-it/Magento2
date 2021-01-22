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

use Magento\Sales\Api\Data\TransactionInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Request\Http as Http;

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
        \Magento\Customer\Model\SessionFactory $sessionFactory
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

        $this->accountConfig = $configProviderFactory->get('account');

        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__.'|'.var_export($this->getRequest()->getParams(), true));

        $this->response = $this->getRequest()->getParams();
        $this->response = array_change_key_case($this->response, CASE_LOWER);

        /**
         * Check if there is a valid response. If not, redirect to home.
         */
        if (count($this->response) === 0 || !array_key_exists('brq_statuscode', $this->response)) {
            return $this->_redirect('/');
        }

        $statusCode = (int)$this->response['brq_statuscode'];

        $this->loadOrder();
        
        $this->helper->setRestoreQuoteLastOrder(false);

        if (!$this->order->getId()) {
            $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED');
        } else {
            $this->quote->load($this->order->getQuoteId());
        }

        $payment = $this->order->getPayment();

        if(!method_exists($payment->getMethodInstance(),'canProcessPostData')){
            return $this->_redirect('/');
        }
        
        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->response)) {
            return $this->_redirect('/');
        }

        $this->logger->addDebug(__METHOD__.'|2|'.var_export($statusCode, true));

        switch ($statusCode) {
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'):
                $this->logger->addDebug(__METHOD__.'|3|'.var_export([$this->order->getStatus(), $this->orderStatusFactory->get(
                        $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'),
                        $this->order
                    )], true));
                if ($this->order->canInvoice()) {
                    $this->logger->addDebug(__METHOD__.'|31|');
                    if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')) {
                        //do nothing - push will change a status
                        $this->logger->addDebug(__METHOD__.'|32|');
                    } else {
                        $this->logger->addDebug(__METHOD__.'|33|');
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

                $payment->getMethodInstance()->processCustomPostData($payment, $this->response);

                /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
                $paymentMethod = $this->order->getPayment()->getMethodInstance();
                $store = $this->order->getStore();

                // Send order confirmation mail if we're supposed to
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                if (!$this->order->getEmailSent()
                    && ($this->accountConfig->getOrderConfirmationEmail($store) === "1"
                        || $paymentMethod->getConfigData('order_email', $store) === "1"
                    )
                ) {
                    if ($this->hasPostData('add_initiated_by_magento', 1) &&
                        $this->hasPostData('brq_primary_service', 'KlarnaKp') &&
                        $this->hasPostData('add_service_action_from_magento', 'reserve') &&
                        !empty($this->response['brq_service_klarnakp_reservationnumber'])
                    ) {

                    } else {
                        if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')) {
                            $this->logger->addDebug(__METHOD__ . '|sendemail|');
                            $this->orderSender->send($this->order, true);
                        }
                    }
                }

                if(
                    ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'))
                    &&
                    !$this->hasPostData('brq_payment_method', 'sofortueberweisung')
                ) {
                    $this->messageManager->addErrorMessage(
                        __(
                            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                            ' error persists, please choose a different payment method.'
                        )
                    );
                    $this->logger->addDebug(__METHOD__.'|5|');
                    return $this->_redirect('/');
                    // $this->redirectFailure();
                }

                $this->logger->addDebug(__METHOD__.'|51|'.var_export(
                    [
                        $this->checkoutSession->getLastSuccessQuoteId(),
                        $this->checkoutSession->getLastQuoteId(),
                        $this->checkoutSession->getLastOrderId(),
                        $this->checkoutSession->getLastRealOrderId(),
                        $this->order->getQuoteId(),
                        $this->order->getId(),
                        $this->order->getIncrementId()
                    ],
               true));

                if (!$this->checkoutSession->getLastSuccessQuoteId() && $this->order->getQuoteId()) {
                    $this->logger->addDebug(__METHOD__.'|52|');
                    $this->checkoutSession->setLastSuccessQuoteId($this->order->getQuoteId());
                }
                if (!$this->checkoutSession->getLastQuoteId() && $this->order->getQuoteId()) {
                    $this->logger->addDebug(__METHOD__.'|53|');
                    $this->checkoutSession->setLastQuoteId($this->order->getQuoteId());
                }
                if (!$this->checkoutSession->getLastOrderId() && $this->order->getId()) {
                    $this->logger->addDebug(__METHOD__.'|54|');
                    $this->checkoutSession->setLastOrderId($this->order->getId());
                }
                if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                    $this->logger->addDebug(__METHOD__.'|55|');
                    $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                }
                $this->logger->addDebug(__METHOD__.'|6|');
                // Redirect to success page
                return $this->redirectSuccess();
                break;
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'):
                $this->logger->addDebug(__METHOD__.'|7|');
                /*
                * Something went wrong, so we're going to have to
                * 1) recreate the quote for the user
                * 2) cancel the order we had to create to even get here
                * 3) redirect back to the checkout page to offer the user feedback & the option to try again
                */

                // StatusCode specified error messages
                $statusCodeAddErrorMessage = array();
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER')] =
                    'According to our system, you have canceled the payment. If this' .
                    ' is not the case, please contact us.';

                $this->messageManager->addErrorMessage(
                    __(
                        $statusCodeAddErrorMessage[$statusCode]
                    )
                );

                if (!$this->recreateQuote()) {
                    $this->logger->addError('Could not recreate the quote.');
                }

                if (!$this->cancelOrder($statusCode)) {
                    $this->logger->addError('Could not cancel the order.');
                }
                $this->logger->addDebug(__METHOD__.'|8|');
                $this->redirectFailure();
                break;
            //no default
        }

        $this->logger->addDebug(__METHOD__.'|9|');
        return $this->_response;
    }

    /**
     * @throws \Buckaroo\Magento2\Exception
     */
    private function loadOrder()
    {
        $brqOrderId = false;

        if (isset($this->response['brq_invoicenumber']) && !empty($this->response['brq_invoicenumber'])) {
            $brqOrderId = $this->response['brq_invoicenumber'];
        }

        if (isset($this->response['brq_ordernumber']) && !empty($this->response['brq_ordernumber'])) {
            $brqOrderId = $this->response['brq_ordernumber'];
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

        if (isset($this->response['brq_transactions']) && !empty($this->response['brq_transactions'])) {
            $trxId = $this->response['brq_transactions'];
        }

        if (isset($this->response['brq_datarequest']) && !empty($this->response['brq_datarequest'])) {
            $trxId = $this->response['brq_datarequest'];
        }

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new \Buckaroo\Magento2\Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Make the previous quote active again, so we can offer the user another opportunity to order (since something
     * went wrong)
     *
     * @return bool
     */
    protected function recreateQuote()
    {
        $this->quote->setIsActive('1');
        $this->quote->setTriggerRecollect('1');
        $this->quote->setReservedOrderId(null);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBuckarooFee(null);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBaseBuckarooFee(null);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBuckarooFeeTaxAmount(null);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBuckarooFeeBaseTaxAmount(null);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBuckarooFeeInclTax(null);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->quote->setBaseBuckarooFeeInclTax(null);

        if ($this->cart->setQuote($this->quote)->save()) {
            return true;
        }
        return false;
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
        $this->logger->addDebug(__METHOD__.'|1|');

        // Mostly the push api already canceled the order, so first check in wich state the order is.
        if ($this->order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            $this->logger->addDebug(__METHOD__.'|5|');
            return true;
        }

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if (!$this->accountConfig->getCancelOnFailed($store)) {
            $this->logger->addDebug(__METHOD__.'|10|');
            return true;
        }

        $this->logger->addDebug(__METHOD__.'|15|');

        if ($this->order->canCancel()) {
            $this->logger->addDebug(__METHOD__.'|20|');

            if ($this->order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode == 'klarnakp') {
                $methodInstanceClass = get_class($this->order->getPayment()->getMethodInstance());
                $methodInstanceClass::$requestOnVoid = false;
            }

            $this->order->cancel();

            if ($this->order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode == 'klarnakp') {
                $this->logger->addDebug(__METHOD__.'|25|');
                return true;
            }

            $this->logger->addDebug(__METHOD__.'|30|');

            $failedStatus = $this->orderStatusFactory->get(
                $statusCode,
                $this->order
            );

            if ($failedStatus) {
                $this->order->setStatus($failedStatus);
            }
            $this->order->save();
            return true;
        }

        $this->logger->addDebug(__METHOD__.'|40|');

        return false;
    }

    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectSuccess()
    {
        $this->logger->addDebug(__METHOD__.'|1|');

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        $this->messageManager->addSuccessMessage(__('Your order has been placed succesfully.'));

        if (
            !empty($this->response['brq_payment_method'])
            &&
            ($this->response['brq_payment_method'] == 'applepay')
            &&
            !empty($this->response['brq_statuscode'])
            &&
            ($this->response['brq_statuscode'] == '190')
            &&
            !empty($this->response['brq_test'])
            &&
            ($this->response['brq_test'] == 'true')
        ) {
            $this->redirectSuccessApplePay();
        }

        $this->logger->addDebug(__METHOD__.'|2|'.var_export($url, true));

        return $this->_redirect($url);
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
        if($this->accountConfig->getFailureRedirectToCheckout($store)){
            $this->logger->addDebug('getFailureRedirectToCheckout');
            if(!$this->customerSession->isLoggedIn()) {
                 $this->logger->addDebug('not isLoggedIn');
                if($this->order->getCustomerId()>0){
                    $this->logger->addDebug('getCustomerId > 0');
                    try {
                        $customer = $this->customerRepository->getById($this->order->getCustomerId());
                        $this->customerSession->setCustomerDataAsLoggedIn($customer);

                        if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                            $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                            $this->logger->addDebug(__METHOD__.'|setLastRealOrderId|');
                            $this->checkoutSession->restoreQuote();
                            $this->logger->addDebug(__METHOD__.'|restoreQuote|');
                        }

                    } catch (\Exception $e) {
                        $this->logger->addError('Could not load customer');
                    }
                }
            }
            $this->logger->addDebug('ready for redirect');
            return $this->_redirect('checkout', ['_fragment' => 'payment']);
        }
        
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getFailureRedirect($store);

        return $this->_redirect($url);
    }

    /**
     * @param $name
     * @param $value
     * @return bool
     */
    private function hasPostData($name, $value)
    {
        if (is_array($value) &&
            isset($this->response[$name]) &&
            in_array($this->response[$name], $value)
        ) {
            return true;
        }

        if (isset($this->response[$name]) &&
            $this->response[$name] == $value
        ) {
            return true;
        }

        return false;
    }
}
