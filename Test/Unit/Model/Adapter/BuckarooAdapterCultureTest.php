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

use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Framework\Locale\Resolver;
use PHPUnit\Framework\TestCase;

/**
 * Requests without a culture of their own (refund, void, capture, giftcard) fall back to the
 * locale of the order's store, not the admin user's or the default store's.
 */
class BuckarooAdapterCultureTest extends TestCase
{
    private function storeLocale(Resolver $resolver, ?int $storeId): string
    {
        $adapter = (new \ReflectionClass(BuckarooAdapter::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(BuckarooAdapter::class, 'localeResolver');
        $property->setValue($adapter, $resolver);

        return (new \ReflectionMethod(BuckarooAdapter::class, 'getStoreLocale'))->invoke($adapter, $storeId);
    }

    public function testUsesTheOrderStoresLocaleAndRestoresTheCurrentOne(): void
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects($this->once())->method('emulate')->with(3)->willReturn('nl_NL');
        $resolver->expects($this->once())->method('revert');
        $resolver->expects($this->never())->method('getLocale');

        $this->assertSame('nl_NL', $this->storeLocale($resolver, 3));
    }

    public function testUsesTheCurrentLocaleWithoutAStore(): void
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects($this->never())->method('emulate');
        $resolver->method('getLocale')->willReturn('en_US');

        $this->assertSame('en_US', $this->storeLocale($resolver, null));
    }
}
