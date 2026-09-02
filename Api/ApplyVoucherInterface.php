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

namespace Buckaroo\Magento2\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;

interface ApplyVoucherInterface
{
    /**
     * Rest method for applying vouchers
     *
     * @param string $voucherCode
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface
     */
    public function apply(string $voucherCode): PayResponseInterface;
}
