<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider as MethodConfigProvider;
use Buckaroo\Magento2\Test\Unit\Stubs\MethodConfigProviderStub;
use PHPUnit\Framework\TestCase;

/**
 * Finding #6.
 *
 * getOrderStatusSuccess()/getOrderStatusFailed() took only a payment method, and getConfig() was
 * passing the *store* into that slot - which then hit explode('_', $store)[2] and an undefined
 * array key. Both now take the store as a second argument and thread it all the way down.
 */
class AccountOrderStatusScopeTest extends TestCase
{
    private function account(?MethodConfigProvider $methodProvider = null): Account
    {
        $account = $this->getMockBuilder(Account::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAccountOrderStatusSuccess',
                'getAccountOrderStatusFailed',
                'getMethodConfigProvider',
            ])
            ->getMock();

        if ($methodProvider !== null) {
            $account->method('getMethodConfigProvider')->willReturn($methodProvider);
        }

        return $account;
    }

    private function methodProvider(bool $active, ?string $success, ?string $failed): MethodConfigProvider
    {
        $provider = $this->getMockBuilder(MethodConfigProviderStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getActiveStatus', 'getOrderStatusSuccess', 'getOrderStatusFailed'])
            ->getMock();
        $provider->method('getActiveStatus')->with(2)->willReturn($active);
        $provider->method('getOrderStatusSuccess')->with(2)->willReturn($success);
        $provider->method('getOrderStatusFailed')->with(2)->willReturn($failed);

        return $provider;
    }

    public function testAccountSuccessStatusIsReadInTheGivenStore(): void
    {
        $account = $this->account();
        $account->expects($this->once())
            ->method('getAccountOrderStatusSuccess')
            ->with(2)
            ->willReturn('buckaroo_magento2_new');

        $this->assertSame('buckaroo_magento2_new', $account->getOrderStatusSuccess(null, 2));
    }

    public function testAccountFailedStatusIsReadInTheGivenStore(): void
    {
        $account = $this->account();
        $account->expects($this->once())->method('getAccountOrderStatusFailed')->with(2)->willReturn('holded');

        $this->assertSame('holded', $account->getOrderStatusFailed(null, 2));
    }

    public function testMethodSuccessStatusWinsAndIsReadInTheSameStore(): void
    {
        $account = $this->account($this->methodProvider(true, 'fraud', null));
        $account->method('getAccountOrderStatusSuccess')->willReturn('processing');

        $this->assertSame('fraud', $account->getOrderStatusSuccess('buckaroo_magento2_ideal', 2));
    }

    public function testMethodStatusIsIgnoredWhenInactive(): void
    {
        $account = $this->account($this->methodProvider(false, 'fraud', null));
        $account->method('getAccountOrderStatusSuccess')->willReturn('processing');

        $this->assertSame('processing', $account->getOrderStatusSuccess('buckaroo_magento2_ideal', 2));
    }

    public function testMethodStatusIsIgnoredWhenNotConfigured(): void
    {
        $account = $this->account($this->methodProvider(true, null, null));
        $account->method('getAccountOrderStatusFailed')->willReturn('canceled');

        $this->assertSame('canceled', $account->getOrderStatusFailed('buckaroo_magento2_ideal', 2));
    }
}
