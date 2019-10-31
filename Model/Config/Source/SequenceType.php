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

namespace TIG\Buckaroo\Model\Config\Source;

class SequenceType implements \Magento\Framework\Option\ArrayInterface
{
    const SEQUENCE_TYPE_ONEOFF = 1;
    const SEQUENCE_TYPE_RECURRING = 0;

    public function toOptionArray()
    {
        $options = [];

        $options[] = ['value' => self::SEQUENCE_TYPE_ONEOFF, 'label' => __('One-Off')];
        $options[] = ['value' => self::SEQUENCE_TYPE_RECURRING, 'label' => __('Recurring')];

        return $options;
    }
}
