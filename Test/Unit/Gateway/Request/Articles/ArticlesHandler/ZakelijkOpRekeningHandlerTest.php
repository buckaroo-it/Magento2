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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ZakelijkOpRekeningHandler;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Zakelijk op Rekening takes unit prices rounded down to the cent and the VAT amount included in
 * that price, also rounded down.
 */
class ZakelijkOpRekeningHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = ZakelijkOpRekeningHandler::class;

    /**
     * @param mixed $quantity
     * @param mixed $unitPrice
     * @param mixed $vat
     * @param array $expected
     */
    #[DataProvider('lineProvider')]
    public function testTheArticleLineIsRoundedDownWithItsIncludedVat(
        $quantity,
        $unitPrice,
        $vat,
        array $expected
    ): void {
        $line = $this->getInstance()->getArticleArrayLine('Product', 7, $quantity, $unitPrice, $vat);

        $this->assertSame(
            ['identifier' => '7', 'description' => 'Product'] + $expected,
            $line
        );
    }

    public static function lineProvider(): array
    {
        return [
            'sub-cent price rounded down, VAT rounded down' => [
                3, 12.349, 21,
                ['quantity' => 3, 'price' => 12.34, 'vatPercentage' => 21.0, 'vatAmount' => 2.14],
            ],
            'VAT amount with a fraction above half a cent is still rounded down' => [
                1, 10.00, 9,
                ['quantity' => 1, 'price' => 10.0, 'vatPercentage' => 9.0, 'vatAmount' => 0.82],
            ],
            'numeric strings are sent as numbers' => [
                '2', '24.20', '21',
                ['quantity' => 2, 'price' => 24.2, 'vatPercentage' => 21.0, 'vatAmount' => 4.2],
            ],
            'a fractional quantity is truncated' => [
                2.7, 5.00, 0,
                ['quantity' => 2, 'price' => 5.0, 'vatPercentage' => 0.0, 'vatAmount' => 0.0],
            ],
            'no VAT given' => [
                1, 5.00, '',
                ['quantity' => 1, 'price' => 5.0, 'vatPercentage' => 0.0, 'vatAmount' => 0.0],
            ],
            'a non-numeric VAT is sent as zero' => [
                1, 5.00, 'high',
                ['quantity' => 1, 'price' => 5.0, 'vatPercentage' => 0.0, 'vatAmount' => 0.0],
            ],
            'a negative line rounds down and carries no VAT amount' => [
                1, -5.555, 21,
                ['quantity' => 1, 'price' => -5.56, 'vatPercentage' => 21.0, 'vatAmount' => 0.0],
            ],
            'a free line carries no VAT amount' => [
                1, 0.0, 21,
                ['quantity' => 1, 'price' => 0.0, 'vatPercentage' => 21.0, 'vatAmount' => 0.0],
            ],
        ];
    }
}
