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

namespace Buckaroo\Magento2\Api;

interface PushRequestInterface
{
    /**
     * @return boolean
     *
     * @api
     */
    public function validate($store = null): bool;

    public function getAmountDebit();

    public function getCurrency();

    public function getCustomerName();

    public function getDescription();

    public function getInvoiceNumber();

    public function getMutationType();

    public function getOrderNumber();

    public function getPayerHash();

    public function getPayment();

    public function getStatusCode();

    public function getStatusCodeDetail();

    public function getStatusMessage();

    public function isTest();

    public function getTransactionMethod();

    public function getTransactionType();

    public function getTransactions();

    public function getOriginalRequest();

    public function setTransactions($transactions);

    public function setAmount($amount);

    public function getData(): array;
}
