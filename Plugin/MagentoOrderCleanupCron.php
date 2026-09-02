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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Model\MagentoOrderCleanupScope;
use Magento\Sales\Model\CronJob\CleanExpiredOrders;

/**
 * Marks the expired-order cleanup cron while it runs, so SkipDeferredPaymentCleanup can recognise
 * cancellations that came from it. The cron's own behaviour is left untouched.
 */
class MagentoOrderCleanupCron
{
    /**
     * @var MagentoOrderCleanupScope
     */
    private MagentoOrderCleanupScope $cleanupScope;

    /**
     * @param MagentoOrderCleanupScope $cleanupScope
     */
    public function __construct(MagentoOrderCleanupScope $cleanupScope)
    {
        $this->cleanupScope = $cleanupScope;
    }

    /**
     * Run the cleanup cron with its scope marked, leaving what it does untouched.
     *
     * @param CleanExpiredOrders $subject
     * @param callable           $proceed
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return mixed
     */
    public function aroundExecute(CleanExpiredOrders $subject, callable $proceed)
    {
        return $this->cleanupScope->run($proceed);
    }
}
