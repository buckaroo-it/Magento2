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

    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        QuoteManagement $quoteManagement,
        OrderManagementInterface $orderManagement,
        GiftcardRemove $giftcardRemoveService,
        BuckarooLoggerInterface $logger
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
        $this->quoteManagement = $quoteManagement;
        $this->orderManagement = $orderManagement;
        $this->giftcardRemoveService = $giftcardRemoveService;
        $this->logger = $logger;
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

        if ($this->response->isSuccess()) {
            $this->saveGroupTransaction();
        } else {
            $this->cancelOrder();
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
    protected function createOrderFromQuote()
    {
        //fix missing email validation
        if ($this->quote->getCustomerEmail() == null) {
            $this->quote->setCustomerEmail(
                $this->quote->getBillingAddress()->getEmail()
            );
        }

        $order = $this->quoteManagement->submit($this->quote);

        //keep the quote active but remove the canceled order from it
        $this->quote->setIsActive(true);
        $this->quote->setOrigOrderId(null);
        $this->quote->setReservedOrderId(null);
        $this->quote->save();
        return $order;
    }
}
