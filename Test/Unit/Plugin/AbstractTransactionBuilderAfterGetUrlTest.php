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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Plugin\AbstractTransactionBuilderAfterGetUrl;
use Buckaroo\Magento2\Service\CookieParamService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cookie-based params are only appended, with their separator, when there are any.
 */
class AbstractTransactionBuilderAfterGetUrlTest extends TestCase
{
    #[DataProvider('returnUrlProvider')]
    public function testReturnUrl(string $url, string $params, string $expected): void
    {
        $service = $this->createMock(CookieParamService::class);
        $service->method('getUrlParamsByCookies')->willReturn($params);

        $plugin = new AbstractTransactionBuilderAfterGetUrl($service);

        $this->assertSame($expected, $plugin->afterGetReturnUrl(null, $url));
    }

    public static function returnUrlProvider(): array
    {
        return [
            'no params, no query' => ['https://shop.test/return', '', 'https://shop.test/return'],
            'no params, with query' => ['https://shop.test/return?a=1', '', 'https://shop.test/return?a=1'],
            'params, no query' => ['https://shop.test/return', 'cid=1', 'https://shop.test/return?cid=1'],
            'params, with query' => ['https://shop.test/return?a=1', 'cid=1', 'https://shop.test/return?a=1&cid=1'],
        ];
    }

    public function testServiceExceptionLeavesUrlUntouched(): void
    {
        $service = $this->createMock(CookieParamService::class);
        $service->method('getUrlParamsByCookies')->willThrowException(new \RuntimeException('boom'));

        $plugin = new AbstractTransactionBuilderAfterGetUrl($service);

        $this->assertSame('https://shop.test/return', $plugin->afterGetReturnUrl(null, 'https://shop.test/return'));
    }
}
