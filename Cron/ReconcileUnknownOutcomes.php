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

namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Service\Transaction\UnknownOutcomeReconciler;

/**
 * Settles orders kept pending because Buckaroo's answer to the payment was lost
 */
class ReconcileUnknownOutcomes
{
    /**
     * @var UnknownOutcomeReconciler
     */
    private UnknownOutcomeReconciler $reconciler;

    /**
     * Constructor
     *
     * @param UnknownOutcomeReconciler $reconciler
     */
    public function __construct(UnknownOutcomeReconciler $reconciler)
    {
        $this->reconciler = $reconciler;
    }

    /**
     * Execute
     *
     * @return $this
     */
    public function execute(): self
    {
        $this->reconciler->execute();

        return $this;
    }
}
