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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;

use Buckaroo\Magento2\Model\Config\Source\VisibleFrontBack;
use Buckaroo\Magento2\Test\BaseTest;

class VisibleFrontBackTest extends BaseTest
{
    protected $instanceClass = VisibleFrontBack::class;

    /**
     * @return array
     */
    public static function toOptionArrayProvider()
    {
        return [
            [
                ['value' => 'frontend', 'label' => 'Frontend']
            ],
            [
                ['value' => 'backend', 'label' => 'Backend']
            ],
            [
                ['value' => 'both', 'label' => 'Frontend and Backend']
            ],
        ];
    }

    /**
     * @param $visibleFrontBack
     *
     * @dataProvider toOptionArrayProvider
     */
    public function testToOptionArray($visibleFrontBack)
    {
        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $found = false;
        foreach ($result as $opt) {
            if ($opt['value'] == $visibleFrontBack['value'] && (string)$opt['label'] == $visibleFrontBack['label']) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @return array
     */
    public static function toArrayProvider()
    {
        return [
                ['Frontend'],
                ['Backend'],
                ['Frontend and Backend'],
        ];
    }

    /**
     * @param $visibleFrontBack
     *
     * @dataProvider toArrayProvider
     */
    public function testToArray($visibleFrontBack)
    {
        $instance = $this->getInstance();
        $result = $instance->toArray();

        // Map display text to actual keys
        $keyMap = [
            'Frontend' => 'frontend',
            'Backend' => 'backend',
            'Frontend and Backend' => 'both'
        ];
        
        $key = $keyMap[$visibleFrontBack];
        $this->assertEquals($visibleFrontBack, $result[$key]);
    }
}
