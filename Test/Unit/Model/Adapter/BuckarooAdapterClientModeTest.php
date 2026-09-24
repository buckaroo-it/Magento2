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

namespace Buckaroo\Magento2\Test\Unit\Model\Adapter;

use Buckaroo\Config\Config;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Service\TransactionOperationValidator;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `buckaroo_magento2/account/active` is an on/off switch; test/live comes from the payment
 * method. Installs upgraded from 1.x can still hold the legacy value 2 (the old "Live"),
 * for example from app/etc/config.php, which a data patch cannot rewrite.
 */
class BuckarooAdapterClientModeTest extends TestCase
{
    public static function enabledAccountProvider(): array
    {
        return [
            'enabled, method live'        => ['1', '2', Config::LIVE_MODE],
            'enabled, method test'        => ['1', '1', Config::TEST_MODE],
            'legacy live, method live'    => ['2', '2', Config::LIVE_MODE],
            'legacy live, method test'    => ['2', '1', Config::TEST_MODE],
        ];
    }

    #[DataProvider('enabledAccountProvider')]
    public function testModeComesFromThePaymentMethodWhenTheAccountIsOn(
        string $accountActive,
        string $methodActive,
        string $expectedMode
    ): void {
        $this->assertSame($expectedMode, $this->getClientMode($accountActive, $methodActive));
    }

    public function testDisabledAccountThrows(): void
    {
        $this->expectException(Exception::class);

        $this->getClientMode('0', '2');
    }

    public function testLegacyLiveAccountStillRejectsADisabledMethod(): void
    {
        $this->expectException(Exception::class);

        $this->getClientMode('2', '0');
    }

    private function getClientMode(string $accountActive, string $methodActive): string
    {
        $methodConfig = $this->createMock(AbstractConfigProvider::class);
        $methodConfig->method('getActive')->willReturn($methodActive);

        $configProviderFactory = $this->createMock(ConfigProviderFactory::class);
        $configProviderFactory->method('get')->willReturn($methodConfig);

        $adapter = new BuckarooAdapter(
            $configProviderFactory,
            $this->createMock(Encryptor::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(ProductMetadataInterface::class),
            $this->createMock(Resolver::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(TransactionOperationValidator::class)
        );

        $method = new \ReflectionMethod(BuckarooAdapter::class, 'getClientMode');

        return $method->invoke($adapter, $accountActive, 1, 'ideal');
    }
}
