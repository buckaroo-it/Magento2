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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountHttpTimeoutTest extends TestCase
{
    public static function valueProvider(): array
    {
        return [
            'configured'     => ['15', 15],
            'integer'        => [20, 20],
            'empty'          => [null, null],
            'blank'          => ['', null],
            'zero'           => ['0', null],
            'negative'       => ['-5', null],
            'over maximum'   => ['600', null],
            'not a number'   => ['abc', null],
            'maximum'        => ['120', 120],
        ];
    }

    /**
     * @param mixed    $stored
     * @param int|null $expected null = falls back to the default
     */
    #[DataProvider('valueProvider')]
    public function testTimeoutsFallBackToTheirDefaults($stored, ?int $expected): void
    {
        $account = $this->getAccount([
            Account::XPATH_ACCOUNT_HTTP_TIMEOUT         => $stored,
            Account::XPATH_ACCOUNT_HTTP_CONNECT_TIMEOUT => $stored,
        ]);

        $this->assertSame($expected ?? Account::HTTP_TIMEOUT_DEFAULT, $account->getHttpTimeout(1));
        $this->assertSame($expected ?? Account::HTTP_CONNECT_TIMEOUT_DEFAULT, $account->getHttpConnectTimeout(1));
    }

    /**
     * @param array $values
     *
     * @return Account
     */
    private function getAccount(array $values): Account
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(fn(string $path) => $values[$path] ?? null);

        $account = (new \ReflectionClass(Account::class))->newInstanceWithoutConstructor();
        $property = (new \ReflectionClass(Account::class))->getProperty('scopeConfig');
        $property->setValue($account, $scopeConfig);

        return $account;
    }
}
