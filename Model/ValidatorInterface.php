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

namespace Buckaroo\Magento2\Model;

interface ValidatorInterface
{
    /**
     * Validates the specified data.
     *
     * @param array|object $data
     *
     * @return bool
     */
    public function validate($data): bool;
}
