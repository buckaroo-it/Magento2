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

namespace Buckaroo\Magento2\Model\Notification;

use Magento\Framework\FlagManager;
use Magento\Framework\View\Layout\Condition\VisibilityConditionInterface;

class CanViewNotification implements VisibilityConditionInterface
{
    /**
     * @var string
     */
    private static $conditionName = 'can_view_buckaroo_notification';

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @param FlagManager $flagManager
     */
    public function __construct(FlagManager $flagManager)
    {
        $this->flagManager = $flagManager;
    }

    /**
     * @inheritdoc
     */
    public function isVisible(array $arguments): bool
    {
        return !$this->flagManager->getFlagData('buckaroo_magento2_view_install_screen');
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::$conditionName;
    }
}
