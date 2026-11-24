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

use Buckaroo\BuckarooClient;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\PaymentMethodCodeResolver;
use Buckaroo\Magento2\Service\TransactionOperationValidator;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The SDK client gets the configured timeouts in the right constructor positions.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooAdapterTimeoutTest extends TestCase
{
    public function testConfiguredTimeoutsReachTheSdk(): void
    {
        $config = $this->buildClient(45, 12)->client()->config();

        $this->assertSame(45, $config->getTimeout());
        $this->assertSame(12, $config->getConnectTimeout());
    }

    /**
     * @param int $timeout
     * @param int $connectTimeout
     *
     * @return BuckarooClient
     */
    private function buildClient(int $timeout, int $connectTimeout): BuckarooClient
    {
        $account = $this->createMock(Account::class);
        $account->method('getActive')->willReturn('1');
        $account->method('getMerchantKey')->willReturn('website-key');
        $account->method('getSecretKey')->willReturn('secret-key');
        $account->method('getHttpTimeout')->willReturn($timeout);
        $account->method('getHttpConnectTimeout')->willReturn($connectTimeout);

        $methodConfig = $this->createMock(AbstractConfigProvider::class);
        $methodConfig->method('getActive')->willReturn('1');

        $factory = $this->createMock(ConfigProviderFactory::class);
        $factory->method('get')->willReturnCallback(
            fn(string $type) => $type === 'account' ? $account : $methodConfig
        );

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        $resolver = $this->createMock(PaymentMethodCodeResolver::class);
        $resolver->method('resolve')->willReturnCallback(fn(string $code) => 'buckaroo_magento2_' . $code);

        $adapter = new BuckarooAdapter(
            $factory,
            $encryptor,
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(ProductMetadataInterface::class),
            $this->createMock(Resolver::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(TransactionOperationValidator::class),
            $resolver
        );

        (new \ReflectionMethod(BuckarooAdapter::class, 'setClientSdk'))
            ->invoke($adapter, 'ideal', 1, false, null, 'nl-NL');

        return (new \ReflectionProperty(BuckarooAdapter::class, 'buckaroo'))->getValue($adapter);
    }
}
