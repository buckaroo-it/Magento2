<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Process extends Action
{
    /**
     * @var Order $order
     */
    protected Order $order;

    /**
     * @var Quote $quote
     */
    protected Quote $quote;

    /**
     * @var OrderPaymentInterface|null
     */
    protected ?OrderPaymentInterface $payment;

    /**
     * @var AccountConfig
     */
    protected AccountConfig $accountConfig;

    /**
     * @var OrderRequestService
     */
    protected OrderRequestService $orderRequestService;

    /**
     * @var OrderStatusFactory
     */
    protected OrderStatusFactory $orderStatusFactory;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $checkoutSession;

    /**
     * @var CustomerSession
     */
    public CustomerSession $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Customer
     */
    protected $customerModel;

    /**
     * @var CustomerFactory
     */
    protected $customerResourceFactory;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var Recreate
     */
    private $quoteRecreate;

    protected $quoteRepository;

    /**
     * @var PushRequestInterface
     */
    protected PushRequestInterface $redirectRequest;

    /**
     * @param Context $context
     * @param Log $logger
     * @param Quote $quote
     * @param AccountConfig $accountConfig
     * @param OrderRequestService $orderRequestService
     * @param OrderStatusFactory $orderStatusFactory
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFactory $customerFactory
     * @param OrderService $orderService
     * @param ManagerInterface $eventManager
     * @param Recreate $quoteRecreate
     * @param RequestPushFactory $requestPushFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Log $logger,
        Quote $quote,
        AccountConfig $accountConfig,
        OrderRequestService $orderRequestService,
        OrderStatusFactory $orderStatusFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        OrderService $orderService,
        ManagerInterface $eventManager,
        Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->orderRequestService = $orderRequestService;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->customerResourceFactory = $customerFactory;
        $this->accountConfig = $accountConfig;
        $this->orderService = $orderService;
        $this->eventManager = $eventManager;
        $this->quoteRecreate = $quoteRecreate;
        $this->quote = $quote;

        // @codingStandardsIgnoreStart
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
        $this->redirectRequest = $requestPushFactory->create();
        // @codingStandardsIgnoreEnd
    }

    /**
     * Process action
     *
     * @return ResponseInterface
     * @throws \Exception
     *
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__ . '|' . var_export($this->redirectRequest->getOriginalRequest(), true));

        /**
         * Check if there is a valid response. If not, redirect to home.
         */
        if (count($this->redirectRequest->getData()) === 0 || empty($this->redirectRequest->getStatusCode())) {
            return $this->handleProcessedResponse('/');
        }

        // Initialize the order, quote, payment
        $this->order = $this->orderRequestService->getOrderByRequest($this->redirectRequest);

        $statusCode = (int)$this->redirectRequest->getStatusCode();
        if (!$this->order->getId()) {
            $statusCode = BuckarooStatusCode::ORDER_FAILED;
        } else {
            $this->quote->load($this->order->getQuoteId());
        }

        $this->payment = $this->order->getPayment();

        $this->checkoutSession->setRestoreQuoteLastOrder(false);

        if ($this->payment) {
            $this->setPaymentOutOfTransit($this->payment);
        }

        if (!method_exists($this->payment->getMethodInstance(), 'canProcessPostData')) {
            return $this->handleProcessedResponse('/');
        }

        if (!$this->payment->getMethodInstance()->canProcessPostData($this->payment, $this->redirectRequest)) {
            return $this->handleProcessedResponse('/');
        }

        $this->logger->addDebug(__METHOD__ . '|2|' . var_export($statusCode, true));

        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_paypal')
            && ($statusCode == BuckarooStatusCode::PENDING_PROCESSING)
        ) {
            $statusCode = BuckarooStatusCode::CANCELLED_BY_USER;
            $this->logger->addDebug(__METHOD__ . '|22|' . var_export($statusCode, true));
        }

        switch ($statusCode) {
            case BuckarooStatusCode::SUCCESS:
            case BuckarooStatusCode::PENDING_PROCESSING:
                $debugInfo = [
                    $this->order->getStatus(),
                    $this->orderStatusFactory->get(BuckarooStatusCode::SUCCESS, $this->order),
                ];
                $this->logger->addDebug(__METHOD__ . '|3|' . var_export($debugInfo, true));

                if ($this->order->canInvoice()) {
                    $this->logger->addDebug(__METHOD__ . '|31|');
                    if ($statusCode == BuckarooStatusCode::SUCCESS) {
                        //do nothing - push will change a status
                        $this->logger->addDebug(__METHOD__ . '|32|');
                    } else {
                        $this->logger->addDebug(__METHOD__ . '|33|');
                        // Set the 'Pending payment status' here
                        $pendingStatus = $this->orderStatusFactory->get(
                            BuckarooStatusCode::PENDING_PROCESSING,
                            $this->order
                        );
                        if ($pendingStatus) {
                            $this->logger->addDebug(__METHOD__ . '|34|' . var_export($pendingStatus, true));
                            $this->order->setStatus($pendingStatus);
                            $this->order->save();
                        }
                    }
                }

                $payment->getMethodInstance()->processCustomPostData($payment, $this->redirectRequest->getData());

                /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
                $paymentMethod = $this->order->getPayment()->getMethodInstance();
                $store = $this->order->getStore();

                // Send order confirmation mail if we're supposed to
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                if (!$this->order->getEmailSent()
                    && (
                        $this->accountConfig->getOrderConfirmationEmail($store) === "1"
                        || $paymentMethod->getConfigData('order_email', $store) === "1"
                    )
                ) {
                    $isKlarnaKpReserve = ($this->redirectRequest->hasPostData('primary_service', 'KlarnaKp')
                        && $this->redirectRequest->hasAdditionalInformation('service_action_from_magento', 'reserve')
                        && !empty($this->redirectRequest->getServiceKlarnakpReservationnumber()));

                    if (!($this->redirectRequest->hasAdditionalInformation('initiated_by_magento', 1)
                        && $isKlarnaKpReserve)
                    ) {
                        if ($statusCode == BuckarooStatusCode::SUCCESS) {
                            $this->logger->addDebug(__METHOD__ . '|sendemail|');
                            $this->orderRequestService->sendOrderEmail($this->order, true);
                        }
                    }
                }

                $pendingCode = BuckarooStatusCode::PENDING_PROCESSING;
                if (($statusCode == $pendingCode)
                    && !$this->redirectRequest->hasPostData('payment_method', 'sofortueberweisung')
                ) {
                    $this->addErrorMessage(
                        __(
                            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
                            ' error persists, please choose a different payment method.'
                        )
                    );
                    $this->logger->addDebug(__METHOD__ . '|5|');

                    $this->removeAmastyGiftcardOnFailed();

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
            case BuckarooStatusCode::ORDER_FAILED:
            case BuckarooStatusCode::FAILED:
            case BuckarooStatusCode::REJECTED:
            case BuckarooStatusCode::CANCELLED_BY_USER:
                return $this->handleFailed($statusCode);
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
     * Create redirect response
     *
     * @return ResponseInterface
     */
    protected function redirectToCheckout()
    {
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
                    } elseif ($this->redirectRequest->hasPostData('primary_service', 'IDIN')) {
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

    /**
     * Get order
     *
     * @return OrderInterface
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     * @return void
     * @throws \Exception
     */
    protected function setPaymentOutOfTransit(OrderPaymentInterface $payment)
    {
        $payment->setAdditionalInformation(BuckarooAdapter::BUCKAROO_PAYMENT_IN_TRANSIT, false)->save();
    }

    /**
     * Remove amasty giftcard from failed order
     *
     * @return void
     */
    protected function removeAmastyGiftcardOnFailed()
    {
        if (class_exists(\Amasty\GiftCardAccount\Model\GiftCardAccount\Repository::class)) {
            $giftcardAccountRepository = $this->_objectManager->get(
                \Amasty\GiftCardAccount\Model\GiftCardAccount\Repository::class
            );

            $giftcardOrderRepository = $this->_objectManager->get(
                \Amasty\GiftCardAccount\Model\GiftCardExtension\Order\Repository::class/** @phpstan-ignore-line */
            );

            try {
                $giftcardOrder = $giftcardOrderRepository->getByOrderId($this->order->getId());

                foreach ($giftcardOrder->getGiftCards() as $giftcardObj) {
                    /** @var \Amasty\GiftCardAccount\Api\Data\GiftCardAccountInterface */
                    $giftcard = $giftcardAccountRepository->getByCode($giftcardObj['code']);
                    $giftcard->setStatus(1);

                    $giftcard->setCurrentValue($giftcard->getCurrentValue() + (float)$giftcardObj['amount']);
                    $giftcardAccountRepository->save($giftcard);
                }
            } catch (\Throwable $th) {
                $this->logger->addDebug($th->getMessage());
                return;
            }
        }
    }

    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return ResponseInterface
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

        if (!empty($this->redirectRequest->getPaymentMethod())
            &&
            ($this->redirectRequest->getPaymentMethod() == 'applepay')
            &&
            !empty($this->redirectRequest->getStatusCode())
            &&
            ($this->redirectRequest->getStatusCode() == '190')
            &&
            !empty($this->redirectRequest->getTest())
            &&
            ($this->redirectRequest->getTest() == 'true')
        ) {
            $this->redirectSuccessApplePay();
        }

        $this->logger->addDebug(__METHOD__ . '|2|' . var_export($url, true));

        return $this->handleProcessedResponse($url);
    }

    /**
     * Redirect if the transaction is of the success Apple Pay type
     *
     * @return void
     */
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
     * Handle failed transactions
     *
     * @param int|null $statusCode
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function handleFailed($statusCode)
    {
        $this->logger->addDebug(__METHOD__ . '|7|');

        $this->eventManager->dispatch('buckaroo_process_handle_failed_before');

        $this->removeAmastyGiftcardOnFailed();

        if (!$this->getSkipHandleFailedRecreate()
            && (!$this->quoteRecreate->recreate($this->quote))) {
            $this->logger->addError('Could not recreate the quote.');
        }

        /*
         * Something went wrong, so we're going to have to
         * 1) recreate the quote for the user
         * 2) cancel the order we had to create to even get here
         * 3) redirect back to the checkout page to offer the user feedback & the option to try again
         */

        // StatusCode specified error messages
        $statusCodeAddErrorMessage = [];
        $statusCodeAddErrorMessage[BuckarooStatusCode::ORDER_FAILED] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[BuckarooStatusCode::FAILED] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[BuckarooStatusCode::REJECTED] =
            'Unfortunately an error occurred while processing your payment. Please try again. If this' .
            ' error persists, please choose a different payment method.';
        $statusCodeAddErrorMessage[
            BuckarooStatusCode::CANCELLED_BY_USER
        ] = 'According to our system, you have canceled the payment. If this' .
            ' is not the case, please contact us.';

        $this->addErrorMessage(
            __(
                $statusCodeAddErrorMessage[$statusCode]
            )
        );

        //skip cancel order for PPE
        if (!empty($this->redirectRequest->getAdditionalInformation('frompayperemail'))) {
            return $this->redirectFailure();
        }

        if (!$this->cancelOrder($statusCode)) {
            $this->logger->addError('Could not cancel the order.');
        }
        $this->logger->addDebug(__METHOD__ . '|8|');
        return $this->redirectFailure();
    }

    /**
     * Function used by external plugins to skip recreate quote
     *
     * @return false
     */
    public function getSkipHandleFailedRecreate()
    {
        return false;
    }

    /**
     * Redirect to Failure url, which means we've got a problem
     *
     * @return ResponseInterface
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
                    $this->setSkipHandleFailedRecreate();
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

    /**
     * Set skip recreating quote on failed transaction
     *
     * @return true
     */
    public function setSkipHandleFailedRecreate()
    {
        return true;
    }

    /**
     * If possible, cancel the order
     *
     * @param int|null $statusCode
     * @return bool
     */
    protected function cancelOrder($statusCode)
    {
        return $this->orderService->cancel($this->order, $statusCode);
    }

    /**
     * Get Response Parameters
     *
     * @return array
     */
    public function getResponseParameters()
    {
        return $this->redirectRequest->getData();
    }
}
