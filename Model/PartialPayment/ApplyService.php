<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\PartialPayment;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\InstrumentInterface;
use Buckaroo\Magento2\Service\Giftcard\AttemptLimit;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\Quote;

/**
 * Applies a partial payment (voucher, giftcard) to a cart.
 *
 * Everything the instruments share happens here once: the cart and method checks, the failed
 * attempt limit, the amount and currency, sending the request, storing the group transaction and
 * reading the answer. Callers (REST, controllers, Magewire) only translate the result.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyService
{
    /**
     * @var RequestBodyBuilder
     */
    private $requestBodyBuilder;

    /**
     * @var SDKTransferFactory
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var GiftcardResponse
     */
    private $gatewayResponse;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @var AttemptLimit
     */
    private $attemptLimit;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var ConfigProviderFactory
     */
    private $configProviderFactory;

    /**
     * @var VerificationGuard
     */
    private $idinVerificationGuard;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param RequestBodyBuilder      $requestBodyBuilder
     * @param SDKTransferFactory      $transferFactory
     * @param ClientInterface         $client
     * @param GiftcardResponse        $gatewayResponse
     * @param PaymentGroupTransaction $groupTransaction
     * @param AttemptLimit            $attemptLimit
     * @param PaymentHelper           $paymentHelper
     * @param ConfigProviderFactory   $configProviderFactory
     * @param VerificationGuard       $idinVerificationGuard
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        RequestBodyBuilder $requestBodyBuilder,
        SDKTransferFactory $transferFactory,
        ClientInterface $client,
        GiftcardResponse $gatewayResponse,
        PaymentGroupTransaction $groupTransaction,
        AttemptLimit $attemptLimit,
        PaymentHelper $paymentHelper,
        ConfigProviderFactory $configProviderFactory,
        VerificationGuard $idinVerificationGuard,
        BuckarooLoggerInterface $logger
    ) {
        $this->requestBodyBuilder = $requestBodyBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->gatewayResponse = $gatewayResponse;
        $this->groupTransaction = $groupTransaction;
        $this->attemptLimit = $attemptLimit;
        $this->paymentHelper = $paymentHelper;
        $this->configProviderFactory = $configProviderFactory;
        $this->idinVerificationGuard = $idinVerificationGuard;
        $this->logger = $logger;
    }

    /**
     * Pay part of the cart with the instrument
     *
     * @param Quote               $quote
     * @param InstrumentInterface $instrument
     *
     * @throws PartialPaymentRejectedException   refused, the message tells the shopper why
     * @throws PartialPaymentUnavailableException unknown outcome, the cause is in the debug log
     *
     * @return ApplyResult
     */
    public function apply(Quote $quote, InstrumentInterface $instrument): ApplyResult
    {
        $this->assertCartCanBePaid($quote, $instrument);
        $instrument->validate($quote);

        $response = $this->send($quote, $instrument);

        try {
            $this->gatewayResponse->set($response, $quote);

            if (!$response->isSuccess()) {
                $this->attemptLimit->registerFailedAttempt($quote);
                throw new PartialPaymentRejectedException(__($this->getRejectionMessage($instrument)));
            }

            return $this->createResult($quote, $response);
        } catch (PartialPaymentRejectedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // The gateway answered, possibly after taking the payment; what happened next is not
            // known to the shop, so the shopper is not told to check the code
            $this->logger->addError(sprintf(
                '[PARTIAL_PAYMENT] | [Service] | [%s:%s] - %s response could not be processed | quote: %s | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $instrument->getServiceCode(),
                $quote->getId(),
                (string)$e
            ));
            throw $this->unavailable($e);
        }
    }

    /**
     * Refuse carts this instrument may not pay before anything reaches the gateway
     *
     * @param Quote               $quote
     * @param InstrumentInterface $instrument
     *
     * @throws PartialPaymentRejectedException
     *
     * @return void
     */
    private function assertCartCanBePaid(Quote $quote, InstrumentInterface $instrument): void
    {
        if (!$quote->getId() || !$quote->getIsActive() || (int)$quote->getItemsCount() === 0) {
            throw new PartialPaymentRejectedException(__("The cart isn't active."));
        }

        try {
            $method = $this->paymentHelper->getMethodInstance($instrument->getMethodCode());
        } catch (LocalizedException $e) {
            throw new PartialPaymentRejectedException(__('This payment method is not available.'), $e);
        }

        if (!$method->isAvailable($quote)) {
            throw new PartialPaymentRejectedException(__('This payment method is not available.'));
        }

        if (!$this->isQuoteCurrencyAllowed($quote, $instrument)) {
            throw new PartialPaymentRejectedException(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }

        try {
            $this->attemptLimit->assertWithinLimit($quote);
        } catch (ApiException $e) {
            throw new PartialPaymentRejectedException(__('Too many attempts. Please try again later.'), $e);
        }

        try {
            $this->idinVerificationGuard->assertVerified($quote);
        } catch (LocalizedException $e) {
            throw new PartialPaymentRejectedException(__($e->getMessage()), $e);
        }
    }

    /**
     * Whether the method accepts the quote currency
     *
     * The request carries the quote currency, as order requests carry the order currency.
     *
     * @param Quote               $quote
     * @param InstrumentInterface $instrument
     *
     * @return bool
     */
    private function isQuoteCurrencyAllowed(Quote $quote, InstrumentInterface $instrument): bool
    {
        $currency = $this->requestBodyBuilder->getCurrency($quote);
        if ($currency === null) {
            return false;
        }

        $allowed = $this->configProviderFactory
            ->get($instrument->getMethodCode())
            ->getAllowedCurrencies($quote->getStoreId());

        return in_array($currency, array_map('trim', (array)$allowed), true);
    }

    /**
     * Send the request and return the gateway answer
     *
     * @param Quote               $quote
     * @param InstrumentInterface $instrument
     *
     * @throws PartialPaymentUnavailableException
     *
     * @return TransactionResponse
     */
    private function send(Quote $quote, InstrumentInterface $instrument): TransactionResponse
    {
        try {
            $body = array_merge(
                $this->requestBodyBuilder->build($quote, $instrument->getServiceCode()),
                $instrument->getRequestFields($quote)
            );
            $response = $this->client->placeRequest($this->transferFactory->create($body));
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[PARTIAL_PAYMENT] | [Service] | [%s:%s] - %s request could not be completed | quote: %s | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $instrument->getServiceCode(),
                $quote->getId(),
                (string)$e
            ));
            throw $this->unavailable($e);
        }

        $transactionResponse = $response['object'] ?? null;
        if (!$transactionResponse instanceof TransactionResponse) {
            $this->logger->addError(sprintf(
                '[PARTIAL_PAYMENT] | [Service] | [%s:%s] - %s request returned no transaction | quote: %s',
                __METHOD__,
                __LINE__,
                $instrument->getServiceCode(),
                $quote->getId()
            ));
            throw $this->unavailable();
        }

        return $transactionResponse;
    }

    /**
     * Exception for an unknown outcome, with a generic message
     *
     * @param \Throwable|null $cause
     *
     * @return PartialPaymentUnavailableException
     */
    private function unavailable(?\Throwable $cause = null): PartialPaymentUnavailableException
    {
        return new PartialPaymentUnavailableException(
            __('The payment service could not be reached. Please try again in a moment.'),
            $cause instanceof \Exception ? $cause : null
        );
    }

    /**
     * Reason the gateway gave, in the instrument's words, or the instrument's fallback
     *
     * @param InstrumentInterface $instrument
     *
     * @return string
     */
    private function getRejectionMessage(InstrumentInterface $instrument): string
    {
        $message = (string)$this->gatewayResponse->getErrorMessage();
        $message = $message === '' ? '' : $instrument->formatRejectionMessage($message);

        return $message !== '' ? $message : (string)$instrument->getFallbackRejectionMessage();
    }

    /**
     * Read the accepted partial payment into a result
     *
     * @param Quote               $quote
     * @param TransactionResponse $response
     *
     * @return ApplyResult
     */
    private function createResult(Quote $quote, TransactionResponse $response): ApplyResult
    {
        $alreadyPaid = (float)$this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
        $remainderDetails = $response->data()['RequiredAction']['PayRemainderDetails'] ?? [];

        $remainder = isset($remainderDetails['RemainderAmount']) && is_scalar($remainderDetails['RemainderAmount'])
            ? (float)$remainderDetails['RemainderAmount']
            : (float)$quote->getGrandTotal() - $alreadyPaid;

        /** @var GroupTransaction $transaction */
        $transaction = $this->groupTransaction->getByTransactionIdWithName((string)$response->getTransactionKey());

        return new ApplyResult(
            is_scalar($response->getAmount()) ? (float)$response->getAmount() : 0.0,
            $remainder,
            $alreadyPaid,
            $remainderDetails['Currency'] ?? $this->requestBodyBuilder->getCurrency($quote),
            $transaction
        );
    }
}
