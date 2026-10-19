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
        $this->assertSame('Logo only (Apple Pay)', end($options)['label']->getText());
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
