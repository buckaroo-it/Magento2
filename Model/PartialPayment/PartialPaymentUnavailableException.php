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
 * The gateway could not be reached or answered with something unusable, so it is unknown whether
 * the partial payment was made. The message is generic; the cause is in the debug log.
 */
class PartialPaymentUnavailableException extends LocalizedException
{
}
