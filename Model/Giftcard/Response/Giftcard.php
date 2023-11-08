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

namespace Buckaroo\Magento2\Model\Giftcard\Response;

use Buckaroo\Magento2\Model\Data\BuckarooResponseData;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\GroupTransaction;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemove;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Giftcard
{
    /**
     * @var TransactionResponse
     */
    protected TransactionResponse $response;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Remove
     */
    protected $giftcardRemoveService;
    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;
    /**
     * @var CartInterface
     */
    private CartInterface $quote;

    /**
     * @var BuckarooResponseData
     */
    private BuckarooResponseData $buckarooResponseData;

    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        QuoteManagement $quoteManagement,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        GiftcardRemove $giftcardRemoveService,
        BuckarooLoggerInterface $logger,
        BuckarooResponseData $buckarooResponseData
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
        $this->quoteManagement = $quoteManagement;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->logger = $logger;
        $this->buckarooResponseData = $buckarooResponseData;
    }

    /**
     * Set raw response data
     *
     * @param TransactionResponse $response
     * @param CartInterface $quote
     * @return void
     * @throws LocalizedException
     */
    public function set(TransactionResponse $response, CartInterface $quote)
    {
        $this->quote = $quote;
        $this->response = $response;
        $this->buckarooResponseData->setResponse($response);

        if ($this->response->isSuccess()) {
            $this->saveGroupTransaction();
            if ($this->quote->getGrandTotal() > $this->response->getAmount()) {
                $this->createOrderFromQuote();
            }
        } else {
            $this->saveGroupTransaction();
            $this->createOrderFromQuote(false);
        }
    }

    /**
     * Get error message
     *
     * @return mixed|string|null
     */
    public function getErrorMessage()
    {
        if ($this->response->isSuccess()) {
            return null;
        }
        if (!empty($this->response->getSubCodeMessage())) {
            return $this->response->getSubCodeMessage();
        }

        if (isset($this->response->getFirstError()['ErrorMessage'])) {
            return $this->response->getFirstError()['ErrorMessage'];
        }
        if (isset($this->response->data()['Status']['Code']['Description'])) {
            return $this->response->data()['Status']['Code']['Description'];
        }
        return '';
    }

    /**
     * Get created group transaction with giftcard name
     *
     * @return GroupTransaction
     */
    public function getCreatedTransaction(): GroupTransaction
    {
        return $this->groupTransaction->getByTransactionIdWithName($this->response->getTransactionKey());
    }

    /**
     * Get transaction key
     *
     * @return string|null
     */
    public function getTransactionKey(): ?string
    {
        if (!isset($this->response->data()['RequiredAction']['PayRemainderDetails']['GroupTransaction'])) {
            return null;
        }
        return $this->response->data()['RequiredAction']['PayRemainderDetails']['GroupTransaction'];
    }

    /**
     * Get already paid amount
     *
     * @return float
     */
    public function getAlreadyPaid(): float
    {
        return $this->groupTransaction->getGroupTransactionAmount(
            $this->quote->getReservedOrderId()
        );
    }

    /**
     * Is successful transaction
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->response->isSuccess();
    }

    /**
     * Get reminder amount
     *
     * @return float
     */
    public function getRemainderAmount()
    {
        if (!isset($this->response->data()['RequiredAction']['PayRemainderDetails']['RemainderAmount']) ||
            !is_scalar($this->response->data()['RequiredAction']['PayRemainderDetails']['RemainderAmount'])
        ) {
            return 0;
        }
        return (float)$this->response->data()['RequiredAction']['PayRemainderDetails']['RemainderAmount'];
    }

    /**
     * Get debit amount
     *
     * @return float
     */
    public function getAmountDebit()
    {
        if (empty($this->response->getAmount()) ||
            !is_scalar($this->response->getAmount())
        ) {
            return 0;
        }
        return (float)$this->response->getAmount();
    }

    /**
     * Get currency
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        if (!isset($this->response->data()['RequiredAction']['PayRemainderDetails']['Currency'])) {
            return null;
        }
        return $this->response->data()['RequiredAction']['PayRemainderDetails']['Currency'];
    }

    public function rollbackAllPartialPayments($order)
    {
        try {
            $transactions = $this->groupTransaction->getGroupTransactionItems($order->getIncrementId());
            foreach ($transactions as $transaction) {
                $this->giftcardRemoveService->remove(
                    $transaction->getTransactionId(),
                    $order->getIncrementId(),
                    $order->getPayment()
                );
            }
        } catch (\Throwable $th) {
            $this->logger->addDebug(sprintf(
                '[GIFTCARD] | [Model] | [%s:%s] - Rollback all Partial Payment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }
    }

    /**
     * Save group transaction data
     *
     * @return void
     */
    protected function saveGroupTransaction()
    {
        $this->groupTransaction->saveGroupTransaction($this->response->data());
    }

    /**
     * Cancel order for failed group transaction
     *
     * @return void
     * @throws LocalizedException
     */
    protected function cancelOrder()
    {
        $order = $this->createOrderFromQuote();
        if ($order instanceof OrderInterface &&
            $order->getEntityId() !== null
        ) {
            $this->orderManagement->cancel($order->getEntityId());
            $order->addCommentToStatusHistory($this->getErrorMessage())
                ->setIsCustomerNotified(false)
                ->setEntityName('invoice')
                ->save();
            $this->rollbackAllPartialPayments($order);
        }
    }

    /**
     * Create order from quote
     *
     * @return AbstractExtensibleModel|OrderInterface|object|null
     * @throws LocalizedException
     */
    protected function createOrderFromQuote($success = true)
    {
        $this->ensureCustomerEmail();

        $order = $this->getExistingOrder() ?? null;

        if ($success) {
            $order = $order ?? $this->quoteManagement->submit($this->quote);
//            $this->updateQuotePaymentAmounts();
        }

        if ($order) {
            $this->quote->setOrigOrderId($order->getEntityId());
        }

        $this->quote->setIsActive(true);
        $this->quote->save();

        return $order;
    }

    /**
     * Ensure customer email is set in the quote.
     */
    protected function ensureCustomerEmail(): void
    {
        if (!$this->quote->getCustomerEmail()) {
            $this->quote->setCustomerEmail($this->quote->getBillingAddress()->getEmail());
        }
    }

    /**
     * Retrieve the existing order if it exists.
     *
     * @return OrderInterface|null
     */
    protected function getExistingOrder(): ?OrderInterface
    {
        $orderId = $this->quote->getOrigOrderId();
        if (!$orderId) {
            return null;
        }

        try {
            return $this->orderRepository->get($orderId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Update the quote's payment amounts based on the currency rate.
     */
    protected function updateQuotePaymentAmounts()
    {
        $rate = 1.0;
        $store = $this->quote->getStore();
        $currency = $store->getCurrentCurrencyCode();
        if ($currency != $store->getBaseCurrencyCode()) {
            $rate = $store->getBaseCurrency()->getRate($currency);
        }

        $amountPaid = $this->response->getAmount();
        $baseAmountPaid = (float)$amountPaid / (float)$rate;

        $this->quote->setBuckarooAlreadyPaid(
            $this->quote->getBuckarooAlreadyPaid() + $amountPaid
        );

        $this->quote->setBaseBuckarooAlreadyPaid(
            $this->quote->getBaseBuckarooAlreadyPaid() + $baseAmountPaid
        );
    }
}
