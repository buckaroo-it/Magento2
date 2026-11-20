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

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Block\Catalog\Product\View;

use Buckaroo\Magento2\Block\Catalog\Product\View\PaypalExpress;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Directory\Model\Currency;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PaypalExpressTest extends TestCase
{
    public static function modeProvider(): array
    {
        return [
            'off'  => ['0', false],
            'test' => ['1', true],
            'live' => ['2', false],
        ];
    }

    /**
     * @dataProvider modeProvider
     */
    public function testIsTestModeFollowsThePaypalMode(string $mode, bool $expected): void
    {
        $this->assertSame($expected, $this->createBlock($mode)->isTestMode());
    }

    public function testTestSdkRequireConfigMapsTheSdkToTheTestCheckout(): void
    {
        $this->assertSame(
            'require.config({map:{"*":{"BuckarooSDK":"\/\/testcheckout.buckaroo.nl\/api\/buckaroosdk\/script"}}});',
            $this->createBlock('1')->getTestSdkRequireConfig()
        );
    }

    public static function merchantIdProvider(): array
    {
        return [
            'test mode uses the sandbox merchant id'       => ['1', 'SANDBOX123', 'SANDBOX123'],
            'test mode without sandbox id uses the live id' => ['1', '', 'LIVE123'],
            'live mode ignores the sandbox merchant id'     => ['2', 'SANDBOX123', 'LIVE123'],
        ];
    }

    /**
     * @dataProvider merchantIdProvider
     */
    public function testConfigUsesTheMerchantIdForTheMode(string $mode, string $sandboxId, string $expected): void
    {
        $config = $this->createBlock($mode, 'LIVE123', $sandboxId)->getConfig();

        $this->assertSame($expected, $config['paypalMerchantId']);
    }

    private function createBlock(string $mode, string $merchantId = '', string $sandboxId = ''): PaypalExpress
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getCode')->willReturn('EUR');
        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrency')->willReturn($currency);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $context = $this->createMock(Context::class);
        $context->method('getStoreManager')->willReturn($storeManager);

        $paypalConfig = $this->createMock(Paypal::class);
        $paypalConfig->method('isPayPalEnabled')->with($store)->willReturn($mode);
        $paypalConfig->method('getExpressMerchantId')->with($store)->willReturn($merchantId);
        $paypalConfig->method('getExpressSandboxMerchantId')->with($store)->willReturn($sandboxId);

        return (new ObjectManager($this))->getObject(PaypalExpress::class, [
            'context'               => $context,
            'configProviderAccount' => $this->createMock(Account::class),
            'encryptor'             => $this->createMock(Encryptor::class),
            'paypalConfig'          => $paypalConfig,
        ]);
    }
}
