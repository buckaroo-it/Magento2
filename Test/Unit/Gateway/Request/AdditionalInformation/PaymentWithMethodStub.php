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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

use Magento\Payment\Model\InfoInterface;

/**
 * A payment that carries getMethod() without being an Order\Payment.
 *
 * The gateway passes objects exposing getMethod() even though InfoInterface does not declare it,
 * so a test double needs it too. Declared as a real interface because
 * MockBuilder::addMethods() was removed in PHPUnit 12.
 */
interface PaymentWithMethodStub extends InfoInterface
{
    /**
     * @return string|null
     */
    public function getMethod();
}
