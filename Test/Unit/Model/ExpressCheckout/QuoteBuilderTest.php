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

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\ExpressCheckout;

use Buckaroo\Magento2\Model\Ideal\QuoteBuilder as IdealQuoteBuilder;
use Buckaroo\Magento2\Model\PaypalExpress\QuoteBuilder as PaypalQuoteBuilder;
use Buckaroo\Magento2\Model\Service\BuyRequestFilter;
use Buckaroo\Magento2\Model\Service\QuoteBuilder as ServiceQuoteBuilder;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use PHPUnit\Framework\TestCase;

class QuoteBuilderTest extends TestCase
{
    public static function builderProvider(): array
    {
        return [
            'paypal express' => [PaypalQuoteBuilder::class],
            'ideal fast checkout' => [IdealQuoteBuilder::class],
            'service' => [ServiceQuoteBuilder::class],
        ];
    }

    /**
     * @dataProvider builderProvider
     */
    public function testBuyRequestOnlyContainsProductSelectionFields(string $builderClass): void
    {
        $dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $dataObjectFactory->method('create')->willReturnCallback(function () {
            return new DataObject();
        });

        $product = $this->createMock(Product::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects($this->once())->method('getById')->with('12')->willReturn($product);

        $buyRequest = null;
        $quote = $this->createMock(Quote::class);
        $quote->expects($this->once())
            ->method('addProduct')
            ->willReturnCallback(function ($addedProduct, $request) use ($product, &$buyRequest) {
                $this->assertSame($product, $addedProduct);
                $buyRequest = $request;
                return $this->createMock(Item::class);
            });

        $builder = new $builderClass(
            $this->createMock(QuoteFactory::class),
            $productRepository,
            $dataObjectFactory,
            $this->createMock(CustomerSession::class),
            new BuyRequestFilter()
        );
        $builder->setFormData(
            'product=12&qty=1&super_attribute%5B93%5D=56&custom_price=1&original_custom_price=1'
        );

        $quoteProperty = new \ReflectionProperty($builder, 'quote');
        $quoteProperty->setAccessible(true);
        $quoteProperty->setValue($builder, $quote);

        $addProduct = new \ReflectionMethod($builder, 'addProduct');
        $addProduct->setAccessible(true);
        $addProduct->invoke($builder);

        $this->assertInstanceOf(DataObject::class, $buyRequest);
        $this->assertSame(
            ['product' => '12', 'qty' => '1', 'super_attribute' => ['93' => '56']],
            $buyRequest->getData()
        );
    }
}
