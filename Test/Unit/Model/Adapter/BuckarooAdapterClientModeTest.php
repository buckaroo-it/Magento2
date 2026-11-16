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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Adapter;

use Buckaroo\Config\Config;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\PaymentMethodCodeResolver;
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
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
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

    public static function serviceCodeProvider(): array
    {
        return [
            'Bancontact via PayPerEmail' => ['bancontactmrcash', 'buckaroo_magento2_mrcash', 'mrcash'],
            'card brand'                 => ['visa', 'buckaroo_magento2_creditcard', 'creditcard'],
            'Przelewy24'                 => ['przelewy24', 'buckaroo_magento2_p24', 'p24'],
        ];
    }

    /**
     * Requests can carry the Buckaroo service code, the config providers are keyed by method code.
     */
    #[DataProvider('serviceCodeProvider')]
    public function testServiceCodeIsLookedUpByItsMethodCode(
        string $serviceCode,
        string $resolvedMethodCode,
        string $expectedProviderType
    ): void {
        $resolver = $this->createMock(PaymentMethodCodeResolver::class);
        $resolver->method('resolve')->with($serviceCode)->willReturn($resolvedMethodCode);

        $requestedTypes = [];
        $mode = $this->getClientMode('1', '2', $serviceCode, $resolver, $requestedTypes);

        $this->assertSame(Config::LIVE_MODE, $mode);
        $this->assertSame([$expectedProviderType], $requestedTypes);
    }

    public function testUnknownServiceCodeIsLookedUpUnchanged(): void
    {
        $resolver = $this->createMock(PaymentMethodCodeResolver::class);
        $resolver->method('resolve')->willReturn(null);

        $requestedTypes = [];
        $this->getClientMode('1', '2', 'afterpay', $resolver, $requestedTypes);

        $this->assertSame(['afterpay'], $requestedTypes);
    }

    /**
     * @param string                         $accountActive
     * @param string                         $methodActive
     * @param string                         $paymentMethod
     * @param PaymentMethodCodeResolver|null $resolver
     * @param array                          $requestedTypes Filled with the types asked from the factory
     *
     * @return string
     */
    private function getClientMode(
        string $accountActive,
        string $methodActive,
        string $paymentMethod = 'ideal',
        ?PaymentMethodCodeResolver $resolver = null,
        array &$requestedTypes = []
    ): string {
        $methodConfig = $this->createMock(AbstractConfigProvider::class);
        $methodConfig->method('getActive')->willReturn($methodActive);

        $configProviderFactory = $this->createMock(ConfigProviderFactory::class);
        $configProviderFactory->method('get')->willReturnCallback(
            function (string $type) use ($methodConfig, &$requestedTypes) {
                $requestedTypes[] = $type;
                return $methodConfig;
            }
        );

        if ($resolver === null) {
            $resolver = $this->createMock(PaymentMethodCodeResolver::class);
            $resolver->method('resolve')->willReturnCallback(fn(string $code) => 'buckaroo_magento2_' . $code);
        }

        $adapter = new BuckarooAdapter(
            $configProviderFactory,
            $this->createMock(Encryptor::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(ProductMetadataInterface::class),
            $this->createMock(Resolver::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(TransactionOperationValidator::class),
            null,
            $resolver
        );

        $method = new \ReflectionMethod(BuckarooAdapter::class, 'getClientMode');

        return $method->invoke($adapter, $accountActive, 1, $paymentMethod);
    }
}
