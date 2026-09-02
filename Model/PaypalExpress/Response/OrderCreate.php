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

namespace Buckaroo\Magento2\Model\PaypalExpress\Response;

use Buckaroo\Magento2\Api\Data\PaypalExpress\OrderCreateResponseInterface;

class OrderCreate implements OrderCreateResponseInterface
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @param string $orderId
     */
    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Get increment cart id
     *
     * @return string
     */
    public function getCartId(): string
    {
        return $this->orderId;
    }
}
