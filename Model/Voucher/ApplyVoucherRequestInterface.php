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

namespace Buckaroo\Magento2\Model\Voucher;

use Magento\Quote\Api\Data\CartInterface;

/**
 * @deprecated Vouchers are sent by \Buckaroo\Magento2\Model\PartialPayment\ApplyService
 * @see \Buckaroo\Magento2\Model\PartialPayment\ApplyService
 */
interface ApplyVoucherRequestInterface
{
    /**
     * Set voucherCode
     *
     * @param string $voucherCode
     *
     * @return ApplyVoucherRequestInterface
     */
    public function setVoucherCode(string $voucherCode): ApplyVoucherRequestInterface;

     /**
      * Set quote
      *
      * @param CartInterface $quote
      *
      * @return ApplyVoucherRequestInterface
      */
    public function setQuote(CartInterface $quote): ApplyVoucherRequestInterface;
}
