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

namespace Buckaroo\Magento2\Api;

interface PushRequestInterface
{
    /**
     * Validate Push Request
     *
     * @param int|string|null $store
     * @return boolean
     *
     * @api
     */
    public function validate($store = null): bool;

    /**
     * Get Amount Debit
     *
     * @return float|string|null
     */
    public function getAmountDebit();

    /**
     * Get Amount Debit
     *
     * @return float|string|null
     */
    public function getAmount();

    /**
     * Get currency
     *
     * @return string|null
     */
    public function getCurrency(): ?string;

    /**
     * Get customer name
     *
     * @return string|null
     */
    public function getCustomerName(): ?string;

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Get invoice number
     *
     * @return string|null
     */
    public function getInvoiceNumber(): ?string;

    /**
     * Get mutation type
     *
     * @return string|null
     */
    public function getMutationType(): ?string;

    /**
     * Get order number
     *
     * @return string|null
     */
    public function getOrderNumber(): ?string;

    /**
     * Get payment key
     *
     * @return string|null
     */
    public function getPayment(): ?string;

    /**
     * Get transaction status code
     *
     * @return string|null
     */
    public function getStatusCode(): ?string;

    /**
     * Get status code details
     *
     * @return string|null
     */
    public function getStatusCodeDetail(): ?string;

    /**
     * Get status message
     *
     * @return string|null
     */
    public function getStatusMessage(): ?string;

    /**
     * Checks if the transaction is for testing
     *
     * @return bool
     */
    public function isTest(): bool;

    /**
     * Get transaction method / service code
     *
     * @return string|null
     */
    public function getTransactionMethod(): ?string;

    /**
     * Get transaction type
     *
     * @return string|null
     */
    public function getTransactionType(): ?string;

    /**
     * Get transaction id
     *
     * @return string|null
     */
    public function getTransactions(): ?string;

    /**
     * Set transactions
     *
     * @param string|string[] $transactions
     * @return void
     */
    public function setTransactions($transactions);

    /**
     * Set Amount Debit
     *
     * @param float|string|null $amount
     * @return void
     */
    public function setAmount($amount): void;

    /**
     * Get property from additional information
     *
     * @param string $propertyName
     * @return string|null
     */
    public function getAdditionalInformation(string $propertyName): ?string;
}
