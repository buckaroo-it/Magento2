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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;

class RefundTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Refund::class;

    /**
     * @return array
     */
    public static function storeProvider(): array
    {
        return [
            'default scope' => [null],
            'store id'      => [1],
        ];
    }

    /**
     * Each config key is read from its own path in the requested store scope.
     *
     * @param int|null $store
     */
    #[DataProvider('storeProvider')]
    public function testGetConfig($store)
    {
        $values = [
            'buckaroo_magento2/refund/enabled'          => '1',
            'buckaroo_magento2/refund/allow_push'       => '0',
            'buckaroo_magento2/refund/pending_approval' => '2',
        ];

        $scopeConfigMock = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
        $scopeConfigMock->expects($this->exactly(3))->method('getValue')
            ->willReturnCallback(function ($path, $scope, $scopeCode) use ($values, $store) {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);
                $this->assertSame($store, $scopeCode);
                $this->assertArrayHasKey($path, $values);
                return $values[$path];
            });

        $instance = $this->getInstance(['scopeConfig' => $scopeConfigMock]);
        $result = $instance->getConfig($store);

        $this->assertSame(
            [
                'enabled'          => '1',
                'allow_push'       => '0',
                'pending_approval' => '2',
            ],
            $result
        );
    }
}
