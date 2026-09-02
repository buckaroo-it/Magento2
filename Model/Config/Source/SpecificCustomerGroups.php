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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Customer\Model\Customer\Source\Group;

class SpecificCustomerGroups extends Group implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $values = parent::toOptionArray();

        array_unshift(
            $values,
            [
                'label' => __('None'),
                'value' => '-1',
            ]
        );

        return $values;
    }
}
