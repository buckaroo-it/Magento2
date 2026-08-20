<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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
