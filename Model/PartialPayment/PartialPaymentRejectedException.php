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

use Magento\Framework\Exception\LocalizedException;

/**
 * The partial payment was refused and the shopper can act on the reason: a wrong or empty code,
 * not enough balance, a cart that cannot be paid this way. The message is safe to show.
 */
class PartialPaymentRejectedException extends LocalizedException
{
}
