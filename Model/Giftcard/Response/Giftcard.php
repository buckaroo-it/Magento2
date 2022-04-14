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

use Magento\Quote\Api\Data\CartInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Giftcard
{
    protected $response;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction
        )
    {
        $this->priceCurrency = $priceCurrency;
        $this->groupTransaction = $groupTransaction;
    }
    /**
     * Set raw response data
     *
     * @param mixed $response
     *
     * @return void
     */
    public function set($response)
    {
        $this->response = $response;
        if ($this->isSuccessful()) {
            $this->saveGroupTransaction();
        }
    }
    protected function saveGroupTransaction()
    {
        $this->groupTransaction->saveGroupTransaction($this->response);
    }
    /**
     * Get already paid amount
     *
     * @param CartInterface $quote
     *
     * @return float
     */
    public function getAlreadyPaid(CartInterface $quote)
    {
        return $this->groupTransaction->getGroupTransactionAmount(
            $quote->getReservedOrderId()
        );
    }
    public function isSuccessful()
    {
        return isset($this->response['Status']['Code']['Code']) && $this->response['Status']['Code']['Code'] == '190';
    }
    /**
     * Get reminder amount
     *
     * @return float
     */
    public function getRemainderAmount()
    {
        if (
            !isset($this->response['RequiredAction']['PayRemainderDetails']['RemainderAmount']) ||
            !is_scalar($this->response['RequiredAction']['PayRemainderDetails']['RemainderAmount'])
        ) {
            return 0;
        }
        return (float)$this->response['RequiredAction']['PayRemainderDetails']['RemainderAmount'];
    }
    /**
     * Get transaction key
     *
     * @return string|null
     */
    public function getTransactionKey()
    {
        if (!isset($this->response['RequiredAction']['PayRemainderDetails']['GroupTransaction'])) {
            return;
        }
        return $this->response['RequiredAction']['PayRemainderDetails']['GroupTransaction'];
    }
    /**
     * Get currency 
     *
     * @return string|null
     */
    public function getCurrency()
    {
        if (!isset($this->response['RequiredAction']['PayRemainderDetails']['Currency'])) {
            return;
        }
        return $this->response['RequiredAction']['PayRemainderDetails']['Currency'];
    }
    public function getErrorMessage()
    {
        if ($this->isSuccessful()) {
            return;
        }
        if (isset($response['Status']['SubCode']['Description'])) {
            return  $response['Status']['SubCode']['Description'];
        }
        if (isset($response['RequestErrors']['ServiceErrors'][0]['ErrorMessage'])) {
            return $response['RequestErrors']['ServiceErrors'][0]['ErrorMessage'];
        }
        if (isset($response['Status']['Code']['Description'])) {
            return $response['Status']['Code']['Description'];
        }
        return '';
    }
}
