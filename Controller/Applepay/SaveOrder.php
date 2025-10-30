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
    protected $registry;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

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
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var QuoteAddressService
     */
    private $quoteAddressService;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param Log $logger
     * @param QuoteManagement $quoteManagement
     * @param CustomerSession $customerSession
     * @param DataObjectFactory $objectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CheckoutSession $checkoutSession
     * @param ConfigProviderFactory $configProviderFactory
     * @param QuoteAddressService $quoteAddressService
     * @param Registry $registry
     * @param Order $order
     * @throws Exception
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
        $this->quoteManagement       = $quoteManagement;
        $this->customerSession       = $customerSession;
        $this->objectFactory         = $objectFactory;
        $this->orderRepository       = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->checkoutSession       = $checkoutSession;
        $this->quoteAddressService   = $quoteAddressService;
        $this->accountConfig         = $configProviderFactory->get('account');
        $this->registry              = $registry;
        $this->order                 = $order;
    }

    /**
     * Save Order
     *
     * @throws LocalizedException
     * @return Json
     */
    public function execute(): Json
    {
        $isPost = $this->getParams();
        $errorMessage = false;
        $data = [];

        if ($isPost && isset($isPost['payment'], $isPost['extra'])) {
            // Log the full request for debugging.
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order | Request: %s',
                __METHOD__,
                __LINE__,
                var_export($isPost, true)
            ));

            // Get the cart/quote.
            $quote = $this->checkoutSession->getQuote();
            $shippingAddress = $quote->getShippingAddress();

            // Set shipping address if quote is not virtual.
            if (!$quote->getIsVirtual() && !$this->quoteAddressService->setShippingAddress($quote, $isPost['payment']['shippingContact'])) {
                return $this->commonResponse([], true);
            }

            // If the shipping method parameter is provided from the client, update the shipping address.
            $shippingMethodParam = $isPost['extra']['shippingMethod'];
            if ($shippingMethodParam && isset($shippingMethodParam['identifier'])) {
                $this->logger->addDebug(sprintf(
                    '[ApplePay] | [Controller] | [%s:%s] - Found Shipping Method in Request: %s',
                    __METHOD__,
                    __LINE__,
                    var_export($shippingMethodParam, true)
                ));
                $shippingAddress->setShippingMethod($shippingMethodParam['identifier']);
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
     * @param  Quote              $quote
     * @param  array|string       $extra
     * @param  array              $payment
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
                    'RequiredAction' => $data->RequiredAction,
                ];
            } else {
                //live mode
                if (!empty($data->Status->Code->Code) &&
                    ($data->Status->Code->Code == '190') &&
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
     * @param  mixed $data
     * @return array
     */
    private function processBuckarooResponse($data): array
    {
        $this->order->loadByIncrementId($data->Order);
        if ($this->order->getId()) {
            $this->checkoutSession
                ->setLastQuoteId($this->order->getQuoteId())
                ->setLastSuccessQuoteId($this->order->getQuoteId())
                ->setLastOrderId($this->order->getId())
                ->setLastRealOrderId($this->order->getIncrementId())
                ->setLastOrderStatus($this->order->getStatus());

            $store = $this->order->getStore();
            $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order - Redirect URL: %s',
                __METHOD__,
                __LINE__,
                $url
            ));
            $data = [
                'RequiredAction' => [
                    'RedirectURL' => $url,
                ],
            ];
        }
        return $data;
    }
}
