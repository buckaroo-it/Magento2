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

namespace Buckaroo\Magento2\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Buckaroo\Magento2\Model\SecondChance\ModuleConflictDetector;

class CheckSecondChanceModuleConflict implements ObserverInterface
{
    /**
     * @var ModuleConflictDetector
     */
    private $conflictDetector;

    /**
     * @param ModuleConflictDetector $conflictDetector
     */
    public function __construct(
        ModuleConflictDetector $conflictDetector
    ) {
        $this->conflictDetector = $conflictDetector;
    }

    /**
     * Check for SecondChance module conflicts when admin loads
     *
     * @param Observer $observer
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer)
    {
        $this->conflictDetector->checkAndNotify();
    }
}
