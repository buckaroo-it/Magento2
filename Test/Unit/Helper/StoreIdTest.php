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


namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\StoreId;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StoreIdTest extends TestCase
{
    /**
     * OrderInterface::getStoreId() is annotated int|null but returns a string, which is what makes
     * strict comparison on a store id unreliable. Normalising has to close that without ever
     * turning an unusable value into 0 - store 0 is the admin store, and silently targeting it
     * would resolve configuration against the wrong scope.
     */
    #[DataProvider('scalarProvider')]
    public function testNormalisesScalars($input, ?int $expected): void
    {
        $this->assertSame($expected, StoreId::normalize($input));
    }

    public static function scalarProvider(): array
    {
        return [
            'null stays null'            => [null, null],
            'int passes through'         => [2, 2],
            'zero is preserved'          => [0, 0],
            'numeric string becomes int' => ['2', 2],
            'padded numeric string'      => ['  2  ', 2],
            'non numeric becomes null'   => ['default', null],
            'empty string becomes null'  => ['', null],
            'blank string becomes null'  => ['   ', null],
            'array becomes null'         => [['2'], null],
            'bool becomes null'          => [true, null],
        ];
    }

    public function testUnwrapsAStoreObject(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn('3');

        $this->assertSame(3, StoreId::normalize($store));
    }
}
