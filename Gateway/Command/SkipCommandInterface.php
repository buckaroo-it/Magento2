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

namespace Buckaroo\Magento2\Gateway\Command;

interface SkipCommandInterface
{
    /**
     * Check if command should be skipped
     *
     * @param array $commandSubject
     *
     * @return bool
     */
    public function isSkip(array $commandSubject): bool;
}
