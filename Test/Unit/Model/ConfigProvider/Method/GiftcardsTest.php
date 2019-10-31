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
namespace TIG\Buckaroo\Test\Unit\Model\ConfigProvider\Method;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards;

class GiftcardsTest extends BaseTest
{
    protected $instanceClass = Giftcards::class;
    /**
     * Test that the config returns the right values
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->withConsecutive(
                ['payment/tig_buckaroo_giftcards/active', ScopeInterface::SCOPE_STORE],
                ['payment/tig_buckaroo_giftcards/allowed_currencies', ScopeInterface::SCOPE_STORE, null]
            )
            ->willReturnOnConsecutiveCalls(true, '');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('giftcards', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('paymentFeeLabel', $result['payment']['buckaroo']['giftcards']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['giftcards']);
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->setMethods(['getValue'])
            ->getMockForAbstractClass();
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('payment/tig_buckaroo_giftcards/active', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEmpty($result);
    }
}
