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

namespace Buckaroo\Magento2\Model\Config\Source;

class LogoColors implements \Magento\Framework\Option\ArrayInterface
{
    const DARK_OPTION = 'Dark';
    const LIGHT_OPTION = 'Light';

    public function toOptionArray()
    {
        $options = [];

        $options[] = ['value' => self::LIGHT_OPTION, 'label' => __('Light')];
        $options[] = ['value' => self::DARK_OPTION, 'label' => __('Dark')];

        return $options;
    }
}
