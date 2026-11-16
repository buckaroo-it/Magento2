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

namespace Buckaroo\Magento2\Test\Unit\Model\Config\Source;

use Buckaroo\Magento2\Model\Config\Source\ApplepayButtonType;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

class ApplepayButtonTypeTest extends BaseTest
{
    protected $instanceClass = ApplepayButtonType::class;

    public function testOffersTheAgreedAppleTypesWithBuyAsDefault()
    {
        $options = $this->getInstance()->toOptionArray();

        // The agreed list: no donate, contribute, support or tip
        $this->assertSame(
            ['buy', 'check-out', 'order', 'pay', 'book', 'subscribe', 'continue', 'rent', 'set-up', 'reload',
             'add-money', 'top-up', 'plain'],
            array_column($options, 'value')
        );
        $this->assertSame('buy', ApplepayButtonType::TYPE_DEFAULT);
        $this->assertSame('Apple Pay logo only', end($options)['label']->getText());
    }

    public static function storedValueProvider(): array
    {
        return [
            'offered type'         => ['check-out', 'check-out'],
            'not configured'       => [null, 'buy'],
            'empty'                => ['', 'buy'],
            'not offered (donate)' => ['donate', 'buy'],
            'invalid apple value'  => ['checkout', 'buy'],
            'unexpected type'      => [['subscribe'], 'buy'],
        ];
    }

    /**
     * @param mixed  $stored
     * @param string $expected
     */
    #[DataProvider('storedValueProvider')]
    public function testNormalizeFallsBackToBuy($stored, string $expected)
    {
        $this->assertSame($expected, $this->getInstance()->normalize($stored));
    }
}
