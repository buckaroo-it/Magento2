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

use Buckaroo\Magento2\Model\Config\Source\GooglepayButtonType;
use Buckaroo\Magento2\Test\BaseTest;
use PHPUnit\Framework\Attributes\DataProvider;

class GooglepayButtonTypeTest extends BaseTest
{
    protected $instanceClass = GooglepayButtonType::class;

    public function testOffersTheGoogleTypesWithBuyAsDefault()
    {
        $options = $this->getInstance()->toOptionArray();

        // The legacy aliases long and short are not offered
        $this->assertSame(
            ['buy', 'checkout', 'order', 'pay', 'book', 'subscribe', 'donate', 'plain'],
            array_column($options, 'value')
        );
        $this->assertSame('buy', GooglepayButtonType::TYPE_DEFAULT);
        $this->assertSame('Google Pay logo only', end($options)['label']->getText());
    }

    public static function storedValueProvider(): array
    {
        return [
            'offered type'        => ['subscribe', 'subscribe'],
            'not configured'      => [null, 'buy'],
            'empty'               => ['', 'buy'],
            'legacy alias long'   => ['long', 'buy'],
            'legacy alias short'  => ['short', 'buy'],
            'apple value'         => ['check-out', 'buy'],
            'unexpected type'     => [['donate'], 'buy'],
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
