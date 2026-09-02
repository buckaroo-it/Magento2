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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;


use PHPUnit\Framework\Attributes\DataProvider;
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
     */
    #[DataProvider('toOptionArrayProvider')]
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
     */
    #[DataProvider('toArrayProvider')]
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
