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

namespace Buckaroo\Magento2\Model\Push;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdealPushProcessor extends DefaultProcessor implements PushProcessorInterface
{
    public const BUCK_PUSH_IDEAL_PAY = 'C021';
    protected const LOCK_PREFIX = 'bk_push_ideal_';

    /**
     * @inheritdoc
     */
    protected function getSpecificPaymentDetails(): array
    {
        return [];
    }
}
