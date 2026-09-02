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

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Builds the data for the ContinueOnIncomplete parameter.
 * Now always sets it for standard iDEAL, as issuer selection is handled by Buckaroo.
 */
class ContinueOnIncompleteDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     * Always adds 'continueOnIncomplete' => '1' (or Buckaroo equivalent)
     * as issuer selection is no longer handled within Magento checkout.
     */
    public function build(array $buildSubject): array
    {
        return ['continueOnIncomplete' => '1'];
    }
}
