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

namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Model\Service\Order as OrderService;

class CancelExpiredOrders
{
    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Cancel expire Transfer and PPE orders
     */
    public function execute()
    {
        $this->orderService->cancelExpiredTransferOrders();
        $this->orderService->cancelExpiredPPEOrders();
        return $this;
    }
}
