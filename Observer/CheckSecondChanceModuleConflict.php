<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
     * @return void
     */
    public function execute(Observer $observer)
    {
        $this->conflictDetector->checkAndNotify();
    }
} 