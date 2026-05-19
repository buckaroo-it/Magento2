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

namespace Buckaroo\Magento2\Model\Notification;

use Magento\Framework\FlagManager;
use Magento\Framework\View\Layout\Condition\VisibilityConditionInterface;

class CanViewNotification implements VisibilityConditionInterface
{
    /**
     * @var string
     */
    private static $conditionName = 'can_view_buckaroo_notification';

    /** @var FlagManager $flagManager */
    private $flagManager;

    /**
     * @param FlagManager $flagManager
     */
    public function __construct(FlagManager $flagManager)
    {
        $this->flagManager = $flagManager;
    }

    /**
     * {@inheritdoc}
     */
    public function isVisible(array $arguments)
    {
        return ! (bool) $this->flagManager->getFlagData('buckaroo_magento2_view_install_screen');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::$conditionName;
    }
}
