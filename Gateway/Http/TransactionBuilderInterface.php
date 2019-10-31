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

namespace TIG\Buckaroo\Gateway\Http;

interface TransactionBuilderInterface
{
    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return TransactionBuilderInterface
     */
    public function setOrder($order);

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder();

    /**
     * @param array $services
     *
     * @return TransactionBuilderInterface
     */
    public function setServices($services);

    /**
     * @return array
     */
    public function getServices();

    /**
     * @param array $customVars
     *
     * @return TransactionBuilderInterface
     */
    public function setCustomVars($customVars);

    /**
     * @return array
     */
    public function getCustomVars();

    /**
     * @param string $method
     *
     * @return TransactionBuilderInterface
     */
    public function setMethod($method);

    /**
     * @return string
     */
    public function getMethod();

    /**
     * @param string $key
     *
     * @return TransactionBuilderInterface
     */
    public function setOriginalTransactionKey($key);

    /**
     * @return string
     */
    public function getOriginalTransactionKey();

    /**
     * @param string $channel
     *
     * @return TransactionBuilderInterface
     */
    public function setChannel($channel);

    /**
     * @return string
     */
    public function getChannel();

    /**
     * @return int|null
     */
    public function getAmount();

    /**
     * @param int $amount
     *
     * @return TransactionBuilderInterface
     */
    public function setAmount($amount);

    /**
     * @return string|null
     */
    public function getCurrency();

    /**
     * @param string $currency
     *
     * @return TransactionBuilderInterface
     */
    public function setCurrency($currency);

    /**
     * @return int|null
     */
    public function getInvoiceId();

    /**
     * @param string $invoiceId
     *
     * @return TransactionBuilderInterface
     */
    public function setInvoiceId($invoiceId);

    /**
     * @param string $type
     *
     * @return TransactionBuilderInterface
     */
    public function setType($type);

    /**
     * @return TransactionBuilderInterface
     */
    public function getType();

    /**
     * @param $url
     *
     * @return TransactionBuilderInterface
     */
    public function setReturnUrl($url);

    /**
     * @return string
     */
    public function getReturnUrl();

    /**
     * @return \TIG\Buckaroo\Gateway\Http\Transaction
     */
    public function build();
}
