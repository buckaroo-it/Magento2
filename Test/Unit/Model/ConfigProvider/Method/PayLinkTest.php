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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\ConfigProvider\Method\PayLink;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PayLinkTest extends BaseTest
{
    protected $instanceClass = PayLink::class;

    public function testHasSendMailReadsThePayPerEmailSetting()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('payment/buckaroo_magento2_payperemail/send_mail', ScopeInterface::SCOPE_STORE, 2)
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);

        $this->assertTrue($instance->hasSendMail(2));
    }
}
