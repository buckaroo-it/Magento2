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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\Method\Applepay;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class SaveOrder extends AbstractApplepay
{
    /**
     * @var Registry|null
     */
    protected ?Registry $registry;

    /**
     * @var QuoteManagement
     */
    protected QuoteManagement $quoteManagement;

    /**
     * @var Order
     */
    protected Order $order;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $checkoutSession;

    /**
     * @var ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $objectFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var QuoteAddressService
     */
    private QuoteAddressService $quoteAddressService;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @param JsonFactory            $resultJsonFactory
     * @param RequestInterface       $request
     * @param Log                    $logger
     * @param QuoteManagement        $quoteManagement
     * @param CustomerSession        $customerSession
     * @param DataObjectFactory      $objectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder  $searchCriteriaBuilder
     * @param CheckoutSession        $checkoutSession
     * @param ConfigProviderFactory  $configProviderFactory
     * @param QuoteAddressService    $quoteAddressService
     * @param Registry               $registry
     * @param Order                  $order
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        Log $logger,
        QuoteManagement $quoteManagement,
        CustomerSession $customerSession,
        DataObjectFactory $objectFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CheckoutSession $checkoutSession,
        ConfigProviderFactory $configProviderFactory,
        QuoteAddressService $quoteAddressService,
        Registry $registry,
        Order $order
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->quoteManagement      = $quoteManagement;
        $this->customerSession      = $customerSession;
        $this->objectFactory        = $objectFactory;
        $this->orderRepository      = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->checkoutSession      = $checkoutSession;
        $this->quoteAddressService  = $quoteAddressService;
        $this->accountConfig        = $configProviderFactory->get('account');
        $this->registry             = $registry;
        $this->order                = $order;
    }

    /**
     * Save Order
     *
     * @return Json
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $isPost = $this->getParams();
        $errorMessage = false;
        $data = [];

        if ($isPost && isset($isPost['payment'], $isPost['extra'])) {
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order | Request: %s',
                __METHOD__,
                __LINE__,
                var_export($isPost, true)
            ));

            // Get the cart/quote.
            $quote = $this->checkoutSession->getQuote();

            // Set shipping address if quote is not virtual.
            if (!$quote->getIsVirtual() && !$this->quoteAddressService->setShippingAddress($quote, $isPost['payment']['shippingContact'])) {
                return $this->commonResponse([], true);
            }
            // Set billing address.
            if (!$this->quoteAddressService->setBillingAddress(
                $quote,
                $isPost['payment']['billingContact'],
                $isPost['payment']['shippingContact']['phoneNumber'] ?? null
            )) {
                return $this->commonResponse([], true);
            }

            // Process quote submission.
            $this->submitQuote($quote, $isPost['extra'], $isPost['payment']);

            // Handle response.
            $data = $this->handleResponse();
        }

        return $this->commonResponse($data, $errorMessage);
    }

    /**
     * Submit the quote.
     *
     * @param Quote $quote
     * @param array|string $extra
     * @param array $payment
     * @return void
     * @throws LocalizedException
     */
    private function submitQuote($quote, $extra, $payment): void
    {
        try {
            $emailAddress = $quote->getShippingAddress()->getEmail();
            if ($quote->getIsVirtual()) {
                $emailAddress = $payment['shippingContact']['emailAddress'] ?? null;
            }

            // If customer is not logged in, mark as guest.
            if (!($this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId())) {
                $quote->setCheckoutMethod('guest')
                    ->setCustomerId(null)
                    ->setCustomerEmail($emailAddress)
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
            }

            $paymentInstance = $quote->getPayment();
            $paymentInstance->setMethod(Applepay::PAYMENT_METHOD_CODE);
            $quote->setPayment($paymentInstance);

            // Invoice handling.
            $invoiceHandlingConfig = $this->accountConfig->getInvoiceHandling($this->order->getStore());
            if ($invoiceHandlingConfig == InvoiceHandlingOptions::SHIPMENT) {
                $paymentInstance->setAdditionalInformation(InvoiceHandlingOptions::INVOICE_HANDLING, $invoiceHandlingConfig);
                $paymentInstance->save();
                $quote->setPayment($paymentInstance);
            }

            // If no shipping method is set for non-virtual quotes, assign the first available rate.
            if (!$quote->getIsVirtual() && !$quote->getShippingAddress()->getShippingMethod()) {
                $rates = $quote->getShippingAddress()->getShippingRatesCollection();
                if ($rates->getSize() > 0) {
                    $firstRate = $rates->getFirstItem();
                    $quote->getShippingAddress()->setShippingMethod($firstRate->getCode());
                    $this->logger->addDebug(sprintf(
                        '[ApplePay] | [Controller] | [%s:%s] - Default Shipping Method Set: %s',
                        __METHOD__,
                        __LINE__,
                        $firstRate->getCode()
                    ));
                }
            }

            // Force totals recalculation.
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();

            // Assign additional payment data.
            $obj = $this->objectFactory->create();
            $obj->setData($extra);
            $quote->getPayment()->getMethodInstance()->assignData($obj);

            // Submit the quote.
            $this->quoteManagement->submit($quote);
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Submit Quote | ERROR: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }
    }

    /**
     * Handle the response after order submission.
     *
     * @return array
     */
    private function handleResponse(): array
    {
        $data = [];
        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            $data = $this->registry->registry('buckaroo_response')[0];
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order Handle Response | Response Data: %s',
                __METHOD__,
                __LINE__,
                var_export($data, true)
            ));

            if (!empty($data->RequiredAction->RedirectURL)) {
                // Test mode response.
                $data = [
                    'RequiredAction' => $data->RequiredAction
                ];
            } else {
                // Live mode response.
                if (!empty($data->Status->Code->Code) &&
                    $data->Status->Code->Code == '190' &&
                    !empty($data->Order)
                ) {
                    $data = $this->processBuckarooResponse($data);
                }
            }
        }

        return $data;
    }

    /**
     * Process Buckaroo response and set order and quote data on session.
     *
     * @param mixed $data
     * @return array
     */
    private function processBuckarooResponse($data): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            'increment_id',
            $data->Order
        )->create();
        $order = $this->orderRepository->getList($searchCriteria)->getFirstItem();

        if ($this->order->getId()) {
            $this->checkoutSession
                ->setLastQuoteId($order->getQuoteId())
                ->setLastSuccessQuoteId($order->getQuoteId())
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());

            $store = $order->getStore();
            $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order - Redirect URL: %s',
                __METHOD__,
                __LINE__,
                $url
            ));
            $data = [
                'RequiredAction' => [
                    'RedirectURL' => $url
                ]
            ];
        }
        return $data;
    }
}
