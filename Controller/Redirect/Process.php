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
declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\Redirect;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfig;
use Buckaroo\Magento2\Model\LockManagerWrapper;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Model\Service\Order as OrderService;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\CustomerFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Process extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    private const GENERAL_ERROR_MESSAGE = 'Unfortunately an error occurred while processing your payment. ' .
    'Please try again. If this error persists, please choose a different payment method.';

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
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepository;

    /**
     * @var OrderService
     */
    protected OrderService $orderService;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;

    /**
     * @var Recreate
     */
    protected Recreate $quoteRecreate;

    /**
     * @var PushRequestInterface
     */
    protected PushRequestInterface $redirectRequest;

    /**
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;

    /**
     * @param Context $context
     * @param BuckarooLoggerInterface $logger
     * @param Quote $quote
     * @param AccountConfig $accountConfig
     * @param OrderRequestService $orderRequestService
     * @param OrderStatusFactory $orderStatusFactory
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param OrderService $orderService
     * @param ManagerInterface $eventManager
     * @param Recreate $quoteRecreate
     * @param RequestPushFactory $requestPushFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        BuckarooLoggerInterface $logger,
        Quote $quote,
        AccountConfig $accountConfig,
        OrderRequestService $orderRequestService,
        OrderStatusFactory $orderStatusFactory,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        OrderService $orderService,
        ManagerInterface $eventManager,
        Recreate $quoteRecreate,
        RequestPushFactory $requestPushFactory,
        LockManagerWrapper $lockManager
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->orderRequestService = $orderRequestService;
        $this->orderStatusFactory = $orderStatusFactory;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->accountConfig = $accountConfig;
        $this->orderService = $orderService;
        $this->eventManager = $eventManager;
        $this->quoteRecreate = $quoteRecreate;
        $this->quote = $quote;
        $this->lockManager = $lockManager;

        // @codingStandardsIgnoreStart
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
        // @codingStandardsIgnoreEnd
        $this->redirectRequest = $requestPushFactory->create();
    }

    /**
     * Process action
     *
     * @return ResponseInterface|void
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $this->logger->addDebug(sprintf(
            '[REDIRECT] | [Controller] | [%s:%s] - Original Request | originalRequest: %s',
            __METHOD__,
            __LINE__,
            var_export($this->redirectRequest->getOriginalRequest(), true)
        ));

        if (count($this->redirectRequest->getData()) === 0 || empty($this->redirectRequest->getStatusCode())) {
            return $this->handleProcessedResponse('/');
        }

        $this->order = $this->orderRequestService->getOrderByRequest($this->redirectRequest);

        $orderIncrementID = $this->order->getIncrementId();
        $this->logger->addDebug(__METHOD__ . '|Lock Name| - ' . var_export($orderIncrementID, true));
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

        if (!$lockAcquired) {
            $this->logger->addError(__METHOD__ . '|lock not acquired|');
            return $this->handleProcessedResponse('/');
        }

        try {
            $statusCode = (int)$this->redirectRequest->getStatusCode();
            if (!$this->order->getId()) {
                $statusCode = BuckarooStatusCode::ORDER_FAILED;
            } else {
                $this->quote->load($this->order->getQuoteId());
            }

            $this->payment = $this->order->getPayment();
            if ($this->payment) {
                $this->setPaymentOutOfTransit($this->payment);
            }

            $this->checkoutSession->setRestoreQuoteLastOrder(false);

            if ($this->skipWaitingOnConsumerForProcessingOrder()) {
                return $this->handleProcessedResponse('/');
            }

            if (($this->payment->getMethodInstance()->getCode() == 'buckaroo_magento2_paypal')
                && ($statusCode == BuckarooStatusCode::PENDING_PROCESSING)
            ) {
                $statusCode = BuckarooStatusCode::CANCELLED_BY_USER;
            }

            $this->logger->addDebug(sprintf(
                '[REDIRECT - %s] | [Controller] | [%s:%s] - Status Code | statusCode: %s',
                $this->payment->getMethod(),
                __METHOD__,
                __LINE__,
                $statusCode
            ));

        } catch (\Exception $e) {
            $this->addErrorMessage('Could not process the request.');
            $this->logger->addError(__METHOD__ . '|Exception|' . $e->getMessage());
        } finally {
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logger->addDebug(__METHOD__ . '|Lock released|');
        }

        return $this->processRedirectByStatus($statusCode);
    }

    /**
     * Handle final response
     *
     * @param string $path
     * @param array $arguments
     *
     * @return ResponseInterface
     */
    public function handleProcessedResponse(string $path, array $arguments = []): ResponseInterface
    {
        return $this->_redirect($path, $arguments);
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param OrderPaymentInterface $payment
     * @return void
     * @throws \Exception
     */
    protected function setPaymentOutOfTransit(OrderPaymentInterface $payment): void
    {
        $payment->setAdditionalInformation(BuckarooAdapter::BUCKAROO_PAYMENT_IN_TRANSIT, false);
    }

    /**
     * Skip process redirect for Processing Order when the status of the request is WaitingOnConsumer
     *
     * @return bool
     */
    public function skipWaitingOnConsumerForProcessingOrder(): bool
    {
        if (in_array(
            $this->payment->getMethod(),
            [
                'buckaroo_magento2_creditcards',
                'buckaroo_magento2_paylink',
                'buckaroo_magento2_payperemail',
                'buckaroo_magento2_transfer'
            ]
        )) {
            $transactionKey = (string)$this->payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY);
            if (strpos($this->redirectRequest->getTransactions(), $transactionKey) === false) {
                return true;
            }

            $orderState = $this->order->getState();
            if ($orderState == Order::STATE_PROCESSING
                && $this->redirectRequest->getStatusCode() == BuckarooStatusCode::WAITING_ON_CONSUMER) {
                return true;
            }
        }

        return false;
    }

    /**
     * Processes a redirect based on the given status code.
     *
     * @param int $statusCode
     * @return ResponseInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function processRedirectByStatus(int $statusCode): ResponseInterface
    {
        $result = null;

        if ($statusCode == BuckarooStatusCode::SUCCESS) {
            $result = $this->processSucceededRedirect($statusCode);
        } elseif ($statusCode == BuckarooStatusCode::PENDING_PROCESSING) {
            $result = $this->processPendingRedirect($statusCode);
        } elseif (in_array($statusCode, [
            BuckarooStatusCode::ORDER_FAILED,
            BuckarooStatusCode::FAILED,
            BuckarooStatusCode::REJECTED,
            BuckarooStatusCode::CANCELLED_BY_USER
        ])) {
            $result = $this->handleFailed($statusCode);
        }

        return $result ?? $this->_response;
    }

    /**
     * Processes a successful redirect based on the given status code.
     *
     *  - Sends a Klarna KP order confirmation using the status code.
     *  - Sets the last quote and order.
     *  - Returns a successful redirect response.
     *
     * @param $statusCode
     * @return ResponseInterface
     * @throws \Exception
     */
    private function processSucceededRedirect($statusCode): ResponseInterface
    {
        $this->sendKlarnaKpOrderConfirmation($statusCode);
        $this->setLastQuoteOrder();

        return $this->redirectSuccess();
    }

    /**
     * Sends a Klarna KP order confirmation based on the given status code.
     *
     * - Retrieves the payment method and store from the order object.
     * - Checks if the order confirmation email has not been sent and if the order confirmation email
     *   setting is enabled either globally or specifically for the payment method.
     * - Validates if the redirect request contains specific post data and additional information related to Klarna KP
     * - If all conditions are met, and the status code is SUCCESS, sends the order confirmation email.
     *
     * @param int $statusCode The status code representing the result of a payment or related process.
     * @return void
     * @throws \Exception If an exception occurs within the called methods.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function sendKlarnaKpOrderConfirmation(int $statusCode): void
    {
        $paymentMethod = $this->payment->getMethodInstance();
        $store = $this->order->getStore();

        $isKlarnaKpReserve = ($this->redirectRequest->hasPostData('primary_service', 'KlarnaKp')
            && $this->redirectRequest->hasAdditionalInformation('service_action_from_magento', 'reserve')
            && !empty($this->redirectRequest->getServiceKlarnakpReservationnumber()));

        if (empty($this->order->getBuckarooReservationNumber()) && $isKlarnaKpReserve) {
            $this->order->setBuckarooReservationNumber($this->redirectRequest->getServiceKlarnakpReservationnumber());
            $this->order->save();
        }

        if (!$this->order->getEmailSent()
            && (
                $this->accountConfig->getOrderConfirmationEmail($store) === "1"
                || $paymentMethod->getConfigData('order_email', $store) === "1"
            )
            && (!($this->redirectRequest->hasAdditionalInformation('initiated_by_magento', 1)
                && $isKlarnaKpReserve
                && $statusCode == BuckarooStatusCode::SUCCESS)
            )
        ) {
            $this->logger->addDebug(sprintf(
                '[REDIRECT - %s] | [Controller] | [%s:%s] - Send Klarna Reservation Mail',
                $this->payment->getMethod(),
                __METHOD__,
                __LINE__
            ));
            $this->orderRequestService->sendOrderEmail($this->order, true);
        }
    }

    /**
     * Sets the last quote and order information in the checkout session.
     *
     *  - Logs the current status of the last successful quote ID, last quote ID, last order ID
     *  - If the last successful quote ID, last quote ID, last order ID, or last real order ID is not set
     *    in the checkout session, it updates them with the corresponding information from the order object.
     *
     * @return void
     */
    private function setLastQuoteOrder(): void
    {
        $this->logger->addDebug(sprintf(
            '[REDIRECT - %s] | [Controller] | [%s:%s] - Set Last Quote Order | currentQuoteOrder: %s',
            $this->payment->getMethod(),
            __METHOD__,
            __LINE__,
            var_export([
                $this->checkoutSession->getLastSuccessQuoteId(),
                $this->checkoutSession->getLastQuoteId(),
                $this->checkoutSession->getLastOrderId(),
                $this->checkoutSession->getLastRealOrderId(),
                $this->order->getQuoteId(),
                $this->order->getId(),
                $this->order->getIncrementId(),
            ], true)
        ));

        if (!$this->checkoutSession->getLastSuccessQuoteId() && $this->order->getQuoteId()) {
            $this->checkoutSession->setLastSuccessQuoteId($this->order->getQuoteId());
        }
        if (!$this->checkoutSession->getLastQuoteId() && $this->order->getQuoteId()) {
            $this->checkoutSession->setLastQuoteId($this->order->getQuoteId());
        }
        if (!$this->checkoutSession->getLastOrderId() && $this->order->getId()) {
            $this->checkoutSession->setLastOrderId($this->order->getId());
        }
        if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
            $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
        }
    }

    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return ResponseInterface
     */
    protected function redirectSuccess(): ResponseInterface
    {
        $this->eventManager->dispatch('buckaroo_process_redirect_success_before');

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        $this->addSuccessMessage(__('Your order has been placed successfully.'));

        $this->quote->setReservedOrderId(null);

        $this->redirectSuccessApplePay();

        $this->logger->addDebug(sprintf(
            '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect Success | redirectURL: %s',
            $this->payment->getMethod(),
            __METHOD__,
            __LINE__,
            $url,
        ));

        return $this->handleProcessedResponse($url);
    }

    /**
     * Add success message to be displayed to the user
     *
     * @param string|Phrase $message
     *
     * @return void
     */
    public function addSuccessMessage($message): void
    {
        $this->messageManager->addSuccessMessage($message);
    }

    /**
     * Redirect if the transaction is of the success Apple Pay type
     *
     * @return void
     */
    protected function redirectSuccessApplePay(): void
    {
        if ($this->redirectRequest->hasPostData('payment_method', 'applepay')
            && $this->redirectRequest->hasPostData('status_code', '190')
            && $this->redirectRequest->hasPostData('test', 'true')
        ) {
            $this->checkoutSession
                ->setLastQuoteId($this->order->getQuoteId())
                ->setLastSuccessQuoteId($this->order->getQuoteId())
                ->setLastOrderId($this->order->getId())
                ->setLastRealOrderId($this->order->getIncrementId())
                ->setLastOrderStatus($this->order->getStatus());
        }
    }

    /**
     * Processes a pending redirect based on the given status code.
     *
     *  - If the order can be invoiced, it sets the 'Pending payment status' and saves the order.
     *  - Sends a Klarna KP order confirmation using the status code.
     *  - Sets the last quote and order.
     *  - Returns a successful redirect response.
     *
     * @param $statusCode
     * @return ResponseInterface
     * @throws LocalizedException
     * @throws \Exception
     */
    private function processPendingRedirect($statusCode): ResponseInterface
    {
        if ($this->order->canInvoice() && !$this->isInvoiceCreatedAfterShipment()) {
            $pendingStatus = $this->orderStatusFactory->get(
                BuckarooStatusCode::PENDING_PROCESSING,
                $this->order
            );
            if ($pendingStatus) {
                $this->logger->addDebug(sprintf(
                    '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect Pending Set Status | pendingStatus: %s',
                    $this->payment->getMethod(),
                    __METHOD__,
                    __LINE__,
                    var_export($pendingStatus, true),
                ));
                $this->order->setStatus($pendingStatus);
                $this->order->save();
            }
        }

        $this->sendKlarnaKpOrderConfirmation($statusCode);

        $this->setLastQuoteOrder();

        return $this->redirectSuccess();
    }

    /**
     * Add error message to be displayed to the user
     *
     * @param string|Phrase $message
     *
     * @return void
     */
    public function addErrorMessage($message): void
    {
        $this->messageManager->addErrorMessage($message);
    }

    /**
     * Remove coupon from failed order if magento enterprise
     *
     * @return void
     */
    protected function removeCoupon()
    {
        if (method_exists($this->order, 'getCouponCode')) {
            $couponCode = $this->order->getCouponCode();
            $couponFactory = $this->_objectManager->get(\Magento\SalesRule\Model\CouponFactory::class);
            if (!(is_object($couponFactory) && method_exists($couponFactory, 'load'))) {
                return;
            }

            $coupon = $couponFactory->load($couponCode, 'code');
            $resourceModel = $this->_objectManager->get(\Magento\SalesRule\Model\Spi\CouponResourceInterface::class);
            if (!(is_object($resourceModel) && method_exists($resourceModel, 'delete'))) {
                return;
            }

            if (is_int($coupon->getCouponId())) {
                $resourceModel->delete($coupon);
            }
        }
    }

    /**
     * Remove amasty giftcard from failed order
     *
     * @return void
     */
    protected function removeAmastyGiftcardOnFailed(): void
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
                $this->logger->addError(sprintf(
                    '[REDIRECT - %s] | [Controller] | [%s:%s] - Remove Amasty Giftcard | [ERROR]: %s',
                    $this->payment->getMethod(),
                    __METHOD__,
                    __LINE__,
                    $th->getMessage()
                ));
                return;
            }
        }
    }

    /**
     * Handle failed transactions
     *
     * @param int|null $statusCode
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException|\Exception
     */
    protected function handleFailed($statusCode): ResponseInterface
    {
        $this->eventManager->dispatch('buckaroo_process_handle_failed_before');

        $this->removeAmastyGiftcardOnFailed();

        if (!$this->getSkipHandleFailedRecreate()
            && (!$this->quoteRecreate->recreate($this->quote))) {
            $this->logger->addError(sprintf(
                '[REDIRECT - %s] | [Controller] | [%s:%s] - Could not Recreate Quote on Failed ',
                $this->payment->getMethod(),
                __METHOD__,
                __LINE__
            ));
        }

        /*
         * Something went wrong, so we're going to have to
         * 1) recreate the quote for the user
         * 2) cancel the order we had to create to even get here
         * 3) redirect back to the checkout page to offer the user feedback & the option to try again
         */
        $this->addErrorMessageByStatus($statusCode);

        //skip cancel order for PPE
        if (!empty($this->redirectRequest->getAdditionalInformation('frompayperemail'))) {
            return $this->redirectFailure();
        }

        if (!$this->cancelOrder($statusCode)) {
            $this->logger->addError(sprintf(
                '[REDIRECT - %s] | [Controller] | [%s:%s] - Could not Cancel the Order.',
                $this->payment->getMethod(),
                __METHOD__,
                __LINE__
            ));
        }

        $this->logger->addDebug(sprintf(
            '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect Failure',
            $this->payment->getMethod(),
            __METHOD__,
            __LINE__
        ));

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
     * Adds an error message to the session based on the given status code.
     *
     * @param int $statusCode
     * @return void
     */
    public function addErrorMessageByStatus(int $statusCode): void
    {
        $statusCodeAddErrorMessage = [];
        $statusCodeAddErrorMessage[BuckarooStatusCode::ORDER_FAILED] = __(self::GENERAL_ERROR_MESSAGE);
        $statusCodeAddErrorMessage[BuckarooStatusCode::FAILED] = __(self::GENERAL_ERROR_MESSAGE);
        $statusCodeAddErrorMessage[BuckarooStatusCode::REJECTED] = __(self::GENERAL_ERROR_MESSAGE);
        $statusCodeAddErrorMessage[BuckarooStatusCode::CANCELLED_BY_USER]
            = __('According to our system, you have canceled the payment. If this is not the case, please contact us.');

        $this->addErrorMessage(__($statusCodeAddErrorMessage[$statusCode]));
    }

    /**
     * Redirect to Failure url, which means we've got a problem
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function redirectFailure(): ResponseInterface
    {
        $store = $this->order->getStore();
        if ($this->accountConfig->getFailureRedirectToCheckout($store)) {
            return $this->redirectOnCheckoutForFailedTransaction();
        }

        $url = $this->accountConfig->getFailureRedirect($store);

        return $this->handleProcessedResponse($url);
    }

    /**
     * Redirects to the checkout page for a failed transaction.
     *
     * - Logs the attempt to redirect to checkout for a failed transaction.
     * - If the customer is not logged in, and there's an associated customer ID with the order,
     *   it attempts to retrieve the customer, log them in, and set necessary session data.
     * - If the last real order ID is not set in the checkout session, and the order has an increment ID,
     *   it sets the last real order ID and may restore the quote.
     * - Finally, it handles the processed response for a redirect to the checkout page, specifically
     *   to the payment section, with a query parameter indicating an error.
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    private function redirectOnCheckoutForFailedTransaction(): ResponseInterface
    {
        $this->setCustomerAndRestoreQuote('failed');

        $this->logger->addDebug(sprintf(
            '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect Failure To Checkout',
            $this->payment->getMethod(),
            __METHOD__,
            __LINE__
        ));

        return $this->handleProcessedResponse('checkout', ['_fragment' => 'payment', '_query' => ['bk_e' => 1]]);
    }

    /**
     * Set customer if it is set on order and not on session and restore quote
     *
     * @param string $status
     * @return void
     */
    protected function setCustomerAndRestoreQuote(string $status): void
    {
        if (!$this->customerSession->isLoggedIn() && $this->order->getCustomerId() > 0) {
            $this->logger->addDebug(sprintf(
                '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect %s To Checkout - Customer is not logged in',
                $this->payment->getMethod(),
                __METHOD__,
                __LINE__,
                $status
            ));
            try {
                $customer = $this->customerRepository->getById($this->order->getCustomerId());
                $this->customerSession->setCustomerDataAsLoggedIn($customer);

                if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                    $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                    if ($status == 'success' || !$this->getSkipHandleFailedRecreate()) {
                        $this->checkoutSession->restoreQuote();
                        $this->logger->addDebug(sprintf(
                            '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect %s To Checkout - Restore Quote',
                            $this->payment->getMethod(),
                            __METHOD__,
                            __LINE__,
                            $status
                        ));
                    }
                    if ($status == 'failed') {
                        $this->setSkipHandleFailedRecreate();
                    }
                }
            } catch (\Exception $e) {
                $this->logger->addError(sprintf(
                    '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect %s To Checkout ' .
                    '- Could not load customer | [ERROR]: %s',
                    $this->payment->getMethod(),
                    __METHOD__,
                    __LINE__,
                    $status,
                    $e->getMessage()
                ));
            }
        }
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
     * @throws LocalizedException
     */
    protected function cancelOrder(?int $statusCode): bool
    {
        return $this->orderService->cancel($this->order, $statusCode);
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
     * Get Response Parameters
     *
     * @return array
     */
    public function getResponseParameters()
    {
        return $this->redirectRequest->getData();
    }

    /**
     * Create redirect response
     *
     * @return ResponseInterface
     */
    protected function redirectToCheckout(): ResponseInterface
    {
        $this->logger->addDebug(sprintf(
            '[REDIRECT - %s] | [Controller] | [%s:%s] - Redirect To Checkout',
            $this->payment->getMethod(),
            __METHOD__,
            __LINE__
        ));

        $this->setCustomerAndRestoreQuote('success');

        return $this->handleProcessedResponse('checkout', ['_query' => ['bk_e' => 1]]);
    }

    /**
     * Is the invoice for the current order is created after shipment
     *
     * @return bool
     */
    private function isInvoiceCreatedAfterShipment(): bool
    {
        return $this->payment->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        ) == InvoiceHandlingOptions::SHIPMENT;
    }
}
