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
 * to support@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model\Config\Source\PaymentMethods;

use TIG\Buckaroo\Model\Config\Source\PaymentMethods\In3Version;
use TIG\Buckaroo\Test\BaseTest;

class In3VersionTest extends BaseTest
{
    protected $instanceClass = In3Version::class;

    /**
     * @return array
     */
    public function toOptionArrayProvider()
    {
        return [
            [
                ['value' => '0', 'label' => 'In3 Flexible']
            ],
            [
                ['value' => '1',  'label' => 'In3 Garant']
            ],
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
