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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source;

use TIG\Buckaroo\Model\Config\Source\EmandateLanguage;
use TIG\Buckaroo\Test\BaseTest;

class EmandateLanguageTest extends BaseTest
{
    protected $instanceClass = EmandateLanguage::class;

    public function testToOptionArray()
    {
        $expectedResult = [
            ['value' => 'nl_NL', 'label' => 'Dutch'],
            ['value' => 'en_US', 'label' => 'English']
        ];

        $instance = $this->getInstance();
        $result = $instance->toOptionArray();

        $this->assertEquals($expectedResult, $result);
    }
}
