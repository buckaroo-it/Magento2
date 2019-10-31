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
namespace TIG\Buckaroo\Test\Unit\Block\Adminhtml\Config\Support;

use TIG\Buckaroo\Block\Adminhtml\Config\Support\SupportTab;
use TIG\Buckaroo\Service\Software\Data;
use TIG\Buckaroo\Test\BaseTest;

class SupportTabTest extends BaseTest
{
    protected $instanceClass = SupportTab::class;

    public function testGetVersionNumber()
    {
        $softwareDataMock = $this->getFakeMock(Data::class)->setMethods(null)->getMock();

        $instance = $this->getInstance(['softwareData' => $softwareDataMock]);
        $result = $instance->getVersionNumber();

        $this->assertEquals(Data::BUCKAROO_VERSION, $result);
    }
}
