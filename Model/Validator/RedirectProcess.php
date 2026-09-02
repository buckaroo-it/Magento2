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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Model\ValidatorInterface;

class RedirectProcess implements ValidatorInterface
{
    /**
     * Validate redirect
     *
     * @param array|object $data
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate($data): bool
    {
        return true;
    }
}
