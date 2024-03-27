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

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Buckaroo\Transaction\Response\TransactionResponse;
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
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveOrder extends AbstractApplepay
{
    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var BuckarooResponseDataInterface
     */
    protected BuckarooResponseDataInterface $buckarooResponseData;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var DataObjectFactory
     */
    private $objectFactory;

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
     * Save Order Constructor
     *
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param BuckarooLoggerInterface $logger
     * @param QuoteManagement $quoteManagement
     * @param CustomerSession $customerSession
     * @param DataObjectFactory $objectFactory
     * @param BuckarooResponseDataInterface $buckarooResponseData
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CheckoutSession $checkoutSession
     * @param ConfigProviderFactory $configProviderFactory
     * @param QuoteAddressService $quoteAddressService
     * @throws Exception
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger,
        QuoteManagement $quoteManagement,
        CustomerSession $customerSession,
        DataObjectFactory $objectFactory,
        BuckarooResponseDataInterface $buckarooResponseData,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CheckoutSession $checkoutSession,
        ConfigProviderFactory $configProviderFactory,
        QuoteAddressService $quoteAddressService
    ) {
        parent::__construct(
            $resultJsonFactory,
            $request,
            $logger
        );

        $this->quoteManagement = $quoteManagement;
        $this->customerSession = $customerSession;
        $this->objectFactory = $objectFactory;
        $this->buckarooResponseData = $buckarooResponseData;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->checkoutSession = $checkoutSession;
        $this->quoteAddressService = $quoteAddressService;
        $this->accountConfig = $configProviderFactory->get('account');
    }

    //phpcs:ignore:Generic.Metrics.NestingLevel

    /**
     * Save Order
     *
     * @return Json
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $isPost = $this->getParams();
        $errorMessage = false;
        $data = [];

        if ($isPost
            && ($payment = $isPost['payment'])
            && ($extra = $isPost['extra'])
        ) {
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order | request: %s',
                __METHOD__,
                __LINE__,
                var_export($isPost, true)
            ));

            // Get Cart
            $quote = $this->checkoutSession->getQuote();

            // Set Address
            if (!$quote->getIsVirtual() && !$this->quoteAddressService->setShippingAddress($quote, $payment['shippingContact'])) {
                return $this->commonResponse(false, true);
            }
            if (!$this->quoteAddressService->setBillingAddress($quote, $payment['billingContact'])) {
                return $this->commonResponse(false, true);
            }

            // Place Order
            $this->submitQuote($quote, $extra, $payment);

            // Handle the response
            $data = $this->handleResponse();
        }

        return $this->commonResponse($data, $errorMessage);
    }

    /**
     * Submit quote
     *
     * @param Quote $quote
     * @param array|string $extra
     * @return void
     * @throws LocalizedException
     */
    private function submitQuote($quote, $extra, $payment)
    {
        try {
            $emailAddress = $quote->getShippingAddress()->getEmail();

            if ($quote->getIsVirtual()) {
                $emailAddress = $payment['shippingContact']['emailAddress'] ?? null;
            }

            if (!($this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId())) {
                $quote->setCheckoutMethod('guest')
                    ->setCustomerId(null)
                    ->setCustomerEmail($emailAddress)
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
            }

            $quote->collectTotals()->save();

            $obj = $this->objectFactory->create();
            $obj->setData($extra);
            $quote->getPayment()->setMethod($obj->getMethod());
            $quote->getPayment()->getMethodInstance()->assignData($obj);

            $this->quoteManagement->submit($quote);
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Submit Quote | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }
    }

    /**
     * Handle the response after placing order
     *
     * @return array
     */
    private function handleResponse()
    {
        $data = [];
        $buckarooResponseData = $this->buckarooResponseData->getResponse();
        if ($buckarooResponseData) {
            $data = $buckarooResponseData->toArray();
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order Handle Response | buckarooResponse: %s',
                __METHOD__,
                __LINE__,
                var_export($data, true)
            ));
            if ($buckarooResponseData->hasRedirect()) {
                //test mode
                $data = [
                    'RequiredAction' => $buckarooResponseData->getRequiredAction()
                ];
            } else {
                //live mode
                if ($buckarooResponseData->isSuccess() && !empty($buckarooResponseData->getOrder())) {
                    $data = $this->processBuckarooResponse($buckarooResponseData, $data);
                }
            }
        }

        return $data;
    }

    /**
     * Set Order and Quote Data on Checkout Session
     *
     * @param TransactionResponse $buckarooResponseData
     * @param array $data
     * @return array
     */
    private function processBuckarooResponse(TransactionResponse $buckarooResponseData, array $data): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            'increment_id',
            $buckarooResponseData->getOrder()
        )->create();
        $order = $this->orderRepository->getList($searchCriteria)->getFirstItem();

        if ($order->getId()) {
            $this->checkoutSession
                ->setLastQuoteId($order->getQuoteId())
                ->setLastSuccessQuoteId($order->getQuoteId())
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus());

            $store = $order->getStore();
            $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order - Redirect URL | redirectURL: %s',
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
