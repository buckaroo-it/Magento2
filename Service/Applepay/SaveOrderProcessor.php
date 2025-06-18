<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SaveOrderProcessor
{
    /** @var QuoteManagement */
    private QuoteManagement $quoteManagement;
    /** @var CustomerSession */
    private CustomerSession $customerSession;
    /** @var DataObjectFactory */
    private DataObjectFactory $objectFactory;
    /** @var BuckarooResponseDataInterface */
    private BuckarooResponseDataInterface $buckarooResponseData;
    /** @var CheckoutSession */
    private CheckoutSession $checkoutSession;
    /** @var ConfigProviderFactory */
    private ConfigProviderFactory $configProviderFactory;
    /** @var QuoteAddressService */
    private QuoteAddressService $quoteAddressService;
    /** @var Order */
    private Order $order;
    /** @var BuckarooLoggerInterface */
    private BuckarooLoggerInterface $logger;
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

    /** Entry‑point called by the controller
     * @throws ExpressMethodsException
     * @throws LocalizedException
     * @throws Exception
     */
    public function place(array $payload): array
    {
        $quote = $this->checkoutSession->getQuote();

        /* 1.Addresses ................................................................... */
        if (!$quote->getIsVirtual()) {
            if (! $this->quoteAddressService->setShippingAddress(
                $quote,
                $payload['payment']['shippingContact'] ?? []
            )) {
                return [];
            }
        }

        if (! $this->quoteAddressService->setBillingAddress(
            $quote,
            $payload['payment']['billingContact'] ?? [],
            $payload['payment']['shippingContact']['phoneNumber'] ?? null
        )) {
            return [];
        }

        /* 2.Shipping method (if any) .................................................... */
        if (!empty($payload['extra']['shippingMethod']['identifier'])) {
            $quote->getShippingAddress()
                ->setShippingMethod($payload['extra']['shippingMethod']['identifier']);
        }

        /* 3.Submit the quote ............................................................ */
        $this->submitQuote($quote, $payload['extra'], $payload['payment']);

        /* 4.Convert the Buckaroo SDK response into JSON for the FE ...................... */
        return $this->handleResponse();
    }

    /* --------------------------------------------------------------------------- */
    /**
     * @throws Exception
     * @throws LocalizedException
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

        /* Payment + invoice handling */
        $paymentInstance = $quote->getPayment()->setMethod(Applepay::CODE);
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
        $paymentInstance->getMethodInstance()->assignData(
            $this->objectFactory->create(['data' => $extra])
        );

        /* Save */
        $this->quoteManagement->submit($quote);
    }

    /* --------------------------------------------------------------------------- */
    /**
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

        $this->logger->addDebug('[ApplePay] Redirect URL: ' . $url);

        return ['RequiredAction' => ['RedirectURL' => $url]];
    }
}
