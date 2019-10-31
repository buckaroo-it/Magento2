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

namespace TIG\Buckaroo\Controller\Redirect;

use Magento\Sales\Api\Data\TransactionInterface;
use TIG\Buckaroo\Logging\Log;

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
     * @var \TIG\Buckaroo\Helper\Data $helper
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
     * @var \TIG\Buckaroo\Model\OrderStatusFactory
     */
    protected $orderStatusFactory;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param \TIG\Buckaroo\Helper\Data                           $helper
     * @param \Magento\Checkout\Model\Cart                        $cart
     * @param \Magento\Sales\Model\Order                          $order
     * @param \Magento\Quote\Model\Quote                          $quote
     * @param TransactionInterface        $transaction
     * @param Log                                                 $logger
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory          $configProviderFactory
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \TIG\Buckaroo\Model\OrderStatusFactory              $orderStatusFactory
     *
     * @throws \TIG\Buckaroo\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \TIG\Buckaroo\Helper\Data $helper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Sales\Model\Order $order,
        \Magento\Quote\Model\Quote $quote,
        TransactionInterface $transaction,
        Log $logger,
        \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \TIG\Buckaroo\Model\OrderStatusFactory $orderStatusFactory
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
        $this->response = array_change_key_case($this->response, CASE_LOWER);

        /**
         * Check if there is a valid response. If not, redirect to home.
         */
        if (count($this->response) === 0 || !array_key_exists('brq_statuscode', $this->response)) {
            return $this->_redirect('/');
        }

        $statusCode = (int)$this->response['brq_statuscode'];

        $this->loadOrder();

        if (!$this->order->getId()) {
            $statusCode = $this->helper->getStatusCode('TIG_BUCKAROO_ORDER_FAILED');
        } else {
            $this->quote->load($this->order->getQuoteId());
        }

        $payment = $this->order->getPayment();

        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->response)) {
            return $this->_redirect('/');
        }

        switch ($statusCode) {
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_SUCCESS'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING'):
                if ($this->order->canInvoice()) {
                    // Set the 'Pending payment status' here
                    $pendingStatus = $this->orderStatusFactory->get(
                        $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_PENDING_PROCESSING'),
                        $this->order
                    );
                    if ($pendingStatus) {
                        $this->order->setStatus($pendingStatus);
                        $this->order->save();
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
                    $this->orderSender->send($this->order, true);
                }

                // Redirect to success page
                $this->redirectSuccess();
                break;
            case $this->helper->getStatusCode('TIG_BUCKAROO_ORDER_FAILED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER'):
                /*
                * Something went wrong, so we're going to have to
                * 1) recreate the quote for the user
                * 2) cancel the order we had to create to even get here
                * 3) redirect back to the checkout page to offer the user feedback & the option to try again
                */

                // StatusCode specified error messages
                $statusCodeAddErrorMessage = array();
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('TIG_BUCKAROO_ORDER_FAILED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_FAILED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_REJECTED')] =
                    'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                    ' error persists, please choose a different payment method.';
                $statusCodeAddErrorMessage[$this->helper->getStatusCode('TIG_BUCKAROO_STATUSCODE_CANCELLED_BY_USER')] =
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

                $this->redirectFailure();
                break;
            //no default
        }

        return $this->_response;
    }

    /**
     * @throws \TIG\Buckaroo\Exception
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
     * @throws \TIG\Buckaroo\Exception
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
            throw new \TIG\Buckaroo\Exception(__('There was no order found by transaction Id'));
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
        // Mostly the push api already canceled the order, so first check in wich state the order is.
        if ($this->order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED) {
            return true;
        }

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if (!$this->accountConfig->getCancelOnFailed($store)) {
            return true;
        }

        if ($this->order->canCancel()) {
            $this->order->cancel();

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

        return false;
    }

    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectSuccess()
    {
        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        $this->messageManager->addSuccessMessage(__('Your order has been placed succesfully.'));

        return $this->_redirect($url);
    }

    /**
     * Redirect to Failure url, which means we've got a problem
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectFailure()
    {
        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getFailureRedirect($store);

        return $this->_redirect($url);
    }
}
