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

use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal;
use Buckaroo\Magento2\Service\Ideal\IssuersService;
use Magento\Framework\App\Config\ScopeConfigInterface;

class IdealTest extends BaseTest
{
    protected $instanceClass = Ideal::class;

    /**
     * Test what happens when the payment method is disabled.
     */
    public function testInactive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertEquals([], $result);
    }

    /**
     * Check if the getConfig function is called for every record.
     */
    public function testGetConfig()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')->willReturnMap([
            // Make the ideal method active
            [
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE,
                null,
                1
            ],
            // Set allowed currencies
            [
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ALLOWED_CURRENCIES),
                ScopeInterface::SCOPE_STORE,
                null,
                'EUR'
            ]
        ]);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig();

        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('buckaroo', $result['payment']);
        $this->assertArrayHasKey('buckaroo_magento2_ideal', $result['payment']['buckaroo']);
        $this->assertArrayHasKey('paymentFeeLabel', $result['payment']['buckaroo']['buckaroo_magento2_ideal']);
        $this->assertArrayHasKey('allowedCurrencies', $result['payment']['buckaroo']['buckaroo_magento2_ideal']);
    }

    /**
     * The issuers from the issuers service are returned in order, each with its logo url added.
     */
    public function testIssuers()
    {
        $issuersServiceMock = $this->getFakeMock(IssuersService::class)
            ->onlyMethods(['get', 'getImageUrlByIssuerId'])
            ->getMock();
        $issuersServiceMock->expects($this->once())->method('get')->willReturn([
            ['id' => 'ABNANL2A', 'name' => 'ABN AMRO', 'code' => 'ABNANL2A'],
            ['id' => 'INGBNL2A', 'name' => 'ING', 'code' => 'INGBNL2A'],
        ]);
        $issuersServiceMock->expects($this->exactly(2))->method('getImageUrlByIssuerId')
            ->willReturnCallback(function ($issuerId) {
                return 'https://example.com/logos/' . $issuerId . '.svg';
            });

        $instance = $this->getInstance(['issuersService' => $issuersServiceMock]);
        $issuers = $instance->getIssuers();

        $this->assertSame(
            [
                [
                    'id' => 'ABNANL2A',
                    'name' => 'ABN AMRO',
                    'code' => 'ABNANL2A',
                    'logo' => 'https://example.com/logos/ABNANL2A.svg',
                ],
                [
                    'id' => 'INGBNL2A',
                    'name' => 'ING',
                    'code' => 'INGBNL2A',
                    'logo' => 'https://example.com/logos/INGBNL2A.svg',
                ],
            ],
            $issuers
        );
    }

    /**
     * Check that the payment fee is return as a false boolean when we have a false-ish value.
     */
    public function testGetPaymentFee()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(null);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals(0, $result);
    }

    /**
     * Check if the payment free is return as a float.
     */
    public function testGetPaymentFeeReturnNumber()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::PAYMENT_FEE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(10.00);

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getPaymentFee();

        $this->assertEquals(10.00, $result);
    }

    /**
     * Test if the getActive magic method returns the correct value.
     */
    public function testGetActive()
    {
        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)
            ->getMock();
        $scopeConfigMock->method('getValue')
            ->with(
                $this->getPaymentMethodConfigPath(Ideal::CODE, AbstractConfigProvider::ACTIVE),
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn('1');

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getActive();

        $this->assertEquals(1, $result);
    }
}
