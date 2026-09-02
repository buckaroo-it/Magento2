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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

/**
 * Interface PayResponseInterface
 *
 * @api
 */
interface PayResponseSetInterface
{
    /**
     * Set any other data
     *
     * @param array $data
     */
    public function setData(array $data);
}
