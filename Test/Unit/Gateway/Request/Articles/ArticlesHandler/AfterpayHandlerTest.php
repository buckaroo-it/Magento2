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

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\AfterpayHandler;
use Magento\Catalog\Helper\Image;

/**
 * Riverty (AfterPay) item lines carry the product image next to the netted unit price.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AfterpayHandlerTest extends \Buckaroo\Magento2\Test\BaseTest
{
    use ArticlesHandlerFixtures;

    private const IMAGE_URL = 'https://shop.example/media/thumbnail.jpg';

    protected $instanceClass = AfterpayHandler::class;

    public function testTheOrderItemLinesCarryTheirImage(): void
    {
        $this->cart([
            $this->quoteItem(['name' => 'Child', 'sku' => 'CHILD', 'qty' => '1.0000', 'parent_item_id' => 90,
                'price_incl_tax' => 24.20, 'row_total_incl_tax' => 24.20, 'tax_percent' => '21.0000']),
            $this->quoteItem(['name' => 'Shirt', 'sku' => 'SHIRT', 'qty' => '2.0000', 'price_incl_tax' => 24.20,
                'row_total_incl_tax' => 48.40, 'tax_percent' => '21.0000', 'discount_amount' => 4.84]),
            $this->quoteItem(['name' => 'Book', 'sku' => 'BOOK', 'qty' => '1.0000', 'price_incl_tax' => 10.90,
                'row_total_incl_tax' => 10.90, 'tax_percent' => '9.0000']),
        ]);

        $this->assertSame(
            ['articles' => [
                $this->imageLine('SHIRT', 'Shirt', 21.0, 2, 21.78, self::IMAGE_URL),
                $this->imageLine('BOOK', 'Book', 9.0, 1, 10.9, self::IMAGE_URL),
            ]],
            $this->afterpay()->getOrderArticlesData($this->order(['grand_total' => 54.46]), $this->payment())
        );
    }

    public function testAtMostNinetyNineItemLinesAreSent(): void
    {
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = $this->quoteItem(['name' => 'P' . $i, 'sku' => 'P' . $i, 'qty' => '1.0000',
                'price_incl_tax' => 1.00, 'row_total_incl_tax' => 1.00, 'tax_percent' => '21.0000']);
        }
        $this->cart($items);

        $articles = $this->afterpay()->getOrderArticlesData($this->order(['grand_total' => 99.00]), $this->payment());

        $this->assertCount(99, $articles['articles']);
        $this->assertSame('P99', end($articles['articles'])['identifier']);
    }

    public function testTheArticleLineNormalisesItsAmounts(): void
    {
        $handler = $this->afterpay();

        $this->assertSame(
            $this->imageLine('5', 'Thing', 21.0, 3, 1234.5, 'img'),
            $handler->getArticleArrayLine('Thing', 5, 2.5, '1,234.50', '21.0000', 'img')
        );
        $this->assertSame(
            ['identifier' => '', 'description' => null, 'vatPercentage' => 0.0, 'quantity' => 2, 'price' => 1.01,
                'imageUrl' => ''],
            $handler->getArticleArrayLine(null, null, 2.4, 1.006)
        );
    }

    public function testARefundLineIsAReturn(): void
    {
        $this->assertSame(
            ['refundType' => 'Return', 'identifier' => 'SHIRT', 'description' => 'Shirt', 'vatPercentage' => 21.0,
                'quantity' => 2, 'price' => 21.78],
            $this->afterpay()->getArticleRefundArrayLine('Shirt', 'SHIRT', 2, 21.78, 21.0)
        );
    }

    /**
     * @return AfterpayHandler
     */
    private function afterpay(): AfterpayHandler
    {
        $image = $this->createMock(Image::class);
        $image->method('init')->willReturnSelf();
        $image->method('setImageFile')->willReturnSelf();
        $image->method('getUrl')->willReturn(self::IMAGE_URL);

        $args = $this->handlerArgs();
        array_splice($args, 9, 0, [$image]);

        return new AfterpayHandler(...$args);
    }

    /**
     * @param string $identifier
     * @param string $description
     * @param float  $vat
     * @param int    $quantity
     * @param float  $price
     * @param string $imageUrl
     *
     * @return array
     */
    private function imageLine(
        string $identifier,
        string $description,
        float $vat,
        int $quantity,
        float $price,
        string $imageUrl
    ): array {
        return $this->line($identifier, $description, $vat, $quantity, $price) + ['imageUrl' => $imageUrl];
    }
}
