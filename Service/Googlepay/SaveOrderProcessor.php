<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Googlepay;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveOrderProcessor
{
    /** @var QuoteManagement */
    private $quoteManagement;
    /** @var CustomerSession */
    private $customerSession;
    /** @var DataObjectFactory */
    private $objectFactory;
    /** @var BuckarooResponseDataInterface */
    private $buckarooResponseData;
    /** @var CheckoutSession */
    private $checkoutSession;
    /** @var ConfigProviderFactory */
    private $configProviderFactory;
    /** @var QuoteAddressService */
    private $quoteAddressService;
    /** @var Order */
    private $order;
    /** @var BuckarooLoggerInterface */
    private $logger;

    /**
     * Constructor
     *
     * @param QuoteManagement $quoteManagement
     * @param CustomerSession $customerSession
     * @param DataObjectFactory $objectFactory
     * @param BuckarooResponseDataInterface $buckarooResponseData
     * @param CheckoutSession $checkoutSession
     * @param ConfigProviderFactory $configProviderFactory
     * @param QuoteAddressService $quoteAddressService
     * @param Order $order
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        QuoteManagement             $quoteManagement,
        CustomerSession             $customerSession,
        DataObjectFactory           $objectFactory,
        BuckarooResponseDataInterface $buckarooResponseData,
        CheckoutSession             $checkoutSession,
        ConfigProviderFactory       $configProviderFactory,
        QuoteAddressService         $quoteAddressService,
        Order                       $order,
        BuckarooLoggerInterface     $logger
    ) {
        $this->quoteManagement       = $quoteManagement;
        $this->customerSession       = $customerSession;
        $this->objectFactory         = $objectFactory;
        $this->buckarooResponseData  = $buckarooResponseData;
        $this->checkoutSession       = $checkoutSession;
        $this->configProviderFactory = $configProviderFactory;
        $this->quoteAddressService   = $quoteAddressService;
        $this->order                 = $order;
        $this->logger                = $logger;
    }

    /**
     * Entry point called by the controller
     *
     * @param array $payload
     * @return array
     * @throws Exception
     * @throws LocalizedException
     * @throws NoSuchEntityException|ExpressMethodsException
     */
    public function place(array $payload): array
    {
        $quote = $this->checkoutSession->getQuote();
        $payload = $this->decodePayloadData($payload);

        if (!$this->setQuoteAddresses($quote, $payload)) {
            return [];
        }

        $this->setQuoteShippingMethod($quote, $payload);
        $this->submitQuote($quote, $payload['extra'], $payload['payment']);

        return $this->handleResponse();
    }

    /**
     * Decode JSON strings in the payload
     *
     * @param array $payload
     * @return array
     */
    private function decodePayloadData(array $payload): array
    {
        if (isset($payload['payment']) && is_string($payload['payment'])) {
            $payload['payment'] = json_decode($payload['payment'], true);
        }
        if (isset($payload['extra']) && is_string($payload['extra'])) {
            $payload['extra'] = json_decode($payload['extra'], true);
        }
        return $payload;
    }

    /**
     * Set shipping and billing addresses on quote
     *
     * @param Quote $quote
     * @param array $payload
     * @return bool
     * @throws ExpressMethodsException
     */
    private function setQuoteAddresses(Quote $quote, array $payload): bool
    {
        if (!$quote->getIsVirtual()) {
            if (!$this->quoteAddressService->setShippingAddress(
                $quote,
                $payload['payment']['shippingContact'] ?? []
            )) {
                $this->logger->addError('[GooglePay SaveOrderProcessor] Failed to set shipping address');
                return false;
            }
        }

        if (!$this->quoteAddressService->setBillingAddress(
            $quote,
            $payload['payment']['billingContact'] ?? [],
            $payload['payment']['shippingContact']['phoneNumber'] ?? null
        )) {
            $this->logger->addError('[GooglePay SaveOrderProcessor] Failed to set billing address');
            return false;
        }

        return true;
    }

    /**
     * Set a shipping method on quote
     *
     * @param Quote $quote
     * @param array $payload
     * @return void
     */
    private function setQuoteShippingMethod(Quote $quote, array $payload)
    {
        if (!empty($payload['extra']['shippingMethod']['identifier'])) {
            $quote->getShippingAddress()
                ->setShippingMethod($payload['extra']['shippingMethod']['identifier']);
            $this->logger->addDebug('[GooglePay SaveOrderProcessor] Shipping method set from payload: ' . $payload['extra']['shippingMethod']['identifier']);
        } else {
            $this->autoSelectFirstShippingMethod($quote);
        }
    }

    /**
     * Auto-select the first available shipping method
     *
     * @param Quote $quote
     * @return void
     */
    private function autoSelectFirstShippingMethod(Quote $quote)
    {
        $this->logger->addDebug('[GooglePay SaveOrderProcessor] No shipping method in payload, auto-selecting...');
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();

        if (!empty($shippingRates)) {
            $firstRate = reset($shippingRates);
            $shippingMethodCode = $firstRate->getCode();
            $shippingAddress->setShippingMethod($shippingMethodCode);
            $this->logger->addDebug('[GooglePay SaveOrderProcessor] Auto-selected shipping method: ' . $shippingMethodCode);
        } else {
            $this->logger->addError('[GooglePay SaveOrderProcessor] No shipping methods available!');
        }
    }

    /**
     * Submit quote and create order
     *
     * @param Quote $quote
     * @param array $extra
     * @param array $payment
     * @throws Exception
     * @throws LocalizedException
     * @return void
     */
    private function submitQuote(Quote $quote, array $extra, array $payment): void
    {
        $email = $quote->getIsVirtual()
            ? ($payment['shippingContact']['emailAddress'] ?? null)
            : $quote->getShippingAddress()->getEmail();

        /* Guest checkout fallback */
        if (!$this->customerSession->getCustomerId()) {
            $quote->setCheckoutMethod('guest')
                ->setCustomerId(null)
                ->setCustomerEmail($email)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
        }

        // Payment + invoice handling
        $paymentInstance = $quote->getPayment()->setMethod(Googlepay::CODE);
        $invoiceCfg      = $this->configProviderFactory
            ->get('account')
            ->getInvoiceHandling($quote->getStore());

        if ($invoiceCfg === InvoiceHandlingOptions::SHIPMENT) {
            $paymentInstance->setAdditionalInformation(
                InvoiceHandlingOptions::INVOICE_HANDLING,
                $invoiceCfg
            );
        }

        /* Totals + additional data */
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        // Set Google Pay payment data in additional information
        if (isset($extra['googlepayPaymentData'])) {
            $paymentInstance->setAdditionalInformation('googlepayPaymentData', $extra['googlepayPaymentData']);
            $this->logger->addDebug('[GooglePay SaveOrderProcessor] Set googlepayPaymentData in additional information');
        } else {
            $this->logger->addError('[GooglePay SaveOrderProcessor] googlepayPaymentData not found in extra data!');
            $this->logger->addDebug('[GooglePay SaveOrderProcessor] Extra data keys: ' . implode(', ', array_keys($extra)));
        }

        $paymentInstance->getMethodInstance()->assignData(
            $this->objectFactory->create(['data' => $extra])
        );

        /* Reserve order ID to enable a proper locking mechanism */
        $quote->reserveOrderId();

        /* Save */
        $this->quoteManagement->submit($quote);
    }

    /**
     * Handle response and prepare redirect or success data
     *
     * @return array
     * @throws Exception
     */
    private function handleResponse(): array
    {
        $response = $this->buckarooResponseData->getResponse();
        if (!$response) {
            return [];
        }

        if ($response->hasRedirect()) {
            return ['RequiredAction' => $response->getRequiredAction()];
        }

        if ($response->isSuccess() && $response->getOrder()) {
            return $this->prepareRedirect($response->getOrder());
        }

        return $response->toArray();
    }

    /**
     * Prepare redirect to success page
     *
     * @param string $incrementId
     * @return array
     * @throws Exception
     */
    private function prepareRedirect(string $incrementId): array
    {
        $this->order->loadByIncrementId($incrementId);

        $this->checkoutSession
            ->setLastQuoteId($this->order->getQuoteId())
            ->setLastSuccessQuoteId($this->order->getQuoteId())
            ->setLastOrderId($this->order->getId())
            ->setLastRealOrderId($this->order->getIncrementId())
            ->setLastOrderStatus($this->order->getStatus());

        /** @var Account $accountConfig */
        $accountConfig = $this->configProviderFactory->get('account');

        $url = $this->order->getStore()->getBaseUrl()
            . $accountConfig->getSuccessRedirect($this->order->getStore());

        $this->logger->addDebug('[GooglePay] Redirect URL: ' . $url);

        return ['RequiredAction' => ['RedirectURL' => $url]];
    }
}
