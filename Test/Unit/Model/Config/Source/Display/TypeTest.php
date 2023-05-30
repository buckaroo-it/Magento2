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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source\Display;

use Buckaroo\Magento2\Model\Config\Source\Display\Type;
use Buckaroo\Magento2\Test\BaseTest;

class TypeTest extends BaseTest
{
    protected $instanceClass = Type::class;

    /**
     * @return array
     */
    public function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 1, 'label' => 'Excluding Tax']
            ],
            [
                ['value' => 2, 'label' => 'Including Tax']
            ],
            [
                ['value' => 3, 'label' => 'Including and Excluding Tax']
            ]
        ];
    }

    /**
     * @param $paymentOption
     *
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray($paymentOption)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertContains($paymentOption, $result);
    }
}
