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

namespace Buckaroo\Magento2\Model\Giftcard\Response;

use Magento\Sales\Model\Order;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Buckaroo\Transaction\Response\TransactionResponse;

class Giftcard
{
    /**
     * @var TransactionResponse
     */
    protected TransactionResponse $response;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $orderManagement;

    private CartInterface $quote;

    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        QuoteManagement $quoteManagement,
        OrderManagementInterface $orderManagement
        )
    {
        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
        $this->quoteManagement = $quoteManagement;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Set raw response data
     *
     * @param TransactionResponse $response
     * @param CartInterface $quote
     * @return void
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
    protected function saveGroupTransaction()
    {
        $this->groupTransaction->saveGroupTransaction($this->response->data());
    }
    /**
     * Get created group transaction with giftcard name
     *
     * @return \Buckaroo\Magento2\Model\GroupTransaction
     */
    public function getCreatedTransaction()
    {
        return $this->groupTransaction->getByTransactionIdWithName($this->response->getTransactionKey());
    }
    /**
     * Get already paid amount
     *
     * @return float
     */
    public function getAlreadyPaid()
    {
        return $this->groupTransaction->getGroupTransactionAmount(
            $this->quote->getReservedOrderId()
        );
    }
    public function isSuccessful()
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
        if (
            !isset($this->response->data()['RequiredAction']['PayRemainderDetails']['RemainderAmount']) ||
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
        if (
            empty($this->response->getAmount()) ||
            !is_scalar($this->response->getAmount())
        ) {
            return 0;
        }
        return (float)$this->response->getAmount();
    }
    /**
     * Get transaction key
     *
     * @return string|null
     */
    public function getTransactionKey()
    {
        if (!isset($this->response->data()['RequiredAction']['PayRemainderDetails']['GroupTransaction'])) {
            return;
        }
        return $this->response->data()['RequiredAction']['PayRemainderDetails']['GroupTransaction'];
    }
    /**
     * Get currency
     *
     * @return string|null
     */
    public function getCurrency()
    {
        if (!isset($this->response->data()['RequiredAction']['PayRemainderDetails']['Currency'])) {
            return;
        }
        return $this->response->data()['RequiredAction']['PayRemainderDetails']['Currency'];
    }
    public function getErrorMessage()
    {
        if ($this->response->isSuccess()) {
            return;
        }
        if (!empty($this->response->getSubCodeMessage())) {
            return  $this->response->getSubCodeMessage();
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
     * Cancel order for failed group transaction
     *
     * @param string $reservedOrderId
     *
     * @return void
     */
    protected function cancelOrder()
    {
        $order = $this->createOrderFromQuote();
        if(
            $order instanceof \Magento\Sales\Api\Data\OrderInterface &&
            $order->getEntityId() !== null
        ) {
            $this->orderManagement->cancel($order->getEntityId());
            $order->addCommentToStatusHistory($this->getErrorMessage())
            ->setIsCustomerNotified(false)
            ->setEntityName('invoice')
            ->save();
        }
    }

    /**
     * Create order from quote
     *
     * @param string $reservedOrderId
     * @return \Magento\Framework\Model\AbstractExtensibleModel|\Magento\Sales\Api\Data\OrderInterface|object|null
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
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
