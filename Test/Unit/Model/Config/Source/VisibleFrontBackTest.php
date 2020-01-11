<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source;

use TIG\Buckaroo\Model\Config\Source\VisibleFrontBack;
use TIG\Buckaroo\Test\BaseTest;

class VisibleFrontBackTest extends BaseTest
{
    protected $instanceClass = VisibleFrontBack::class;

    /**
     * @return array
     */
    public function toOptionArrayProvider()
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

        $this->assertContains($visibleFrontBack, $result);
    }

    /**
     * @return array
     */
    public function toArrayProvider()
    {
        return [
                ['frontend' => 'Frontend'],
                ['backend' => 'Backend'],
                ['both' => 'Frontend and Backend'],
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

        $this->assertContains($visibleFrontBack, $result);
    }

}
