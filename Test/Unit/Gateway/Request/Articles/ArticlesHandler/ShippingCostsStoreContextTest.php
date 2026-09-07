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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Articles\ArticlesHandler;

use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use PHPUnit\Framework\TestCase;

/**
 * The shipping tax must be resolved in the order's own store/address context.
 *
 * A capture or refund runs in an admin/CLI context, so falling back to the current store sends a
 * different ArticleVat than the reserve did (e.g. 21% NL instead of 19% DE).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ShippingCostsStoreContextTest extends TestCase
{
    private const SHIPPING_TAX_CLASS_ID = 4;
    private const SHIPPING_VAT_PERCENT = 19.0;

    public function testShippingTaxIsResolvedWithTheOrdersStoreAndAddresses(): void
    {
        $storeMock = $this->createMock(Store::class);
        $shippingAddressMock = $this->createMock(Address::class);
        $billingAddressMock = $this->createMock(Address::class);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getShippingInclTax')->willReturn(4.95);
        $orderMock->method('getStore')->willReturn($storeMock);
        $orderMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $orderMock->method('getBillingAddress')->willReturn($billingAddressMock);

        $taxCalculation = $this->createMock(Calculation::class);
        $rateRequest = new \Magento\Framework\DataObject();
        $taxCalculation->expects($this->once())
            ->method('getRateRequest')
            ->with($shippingAddressMock, $billingAddressMock, null, $storeMock)
            ->willReturn($rateRequest);
        $taxCalculation->method('getRate')->willReturn(self::SHIPPING_VAT_PERCENT);

        $taxConfig = $this->createMock(Config::class);
        $taxConfig->expects($this->once())
            ->method('getShippingTaxClass')
            ->with($storeMock)
            ->willReturn(self::SHIPPING_TAX_CLASS_ID);

        $handler = new KlarnaKpHandler(
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(BuckarooLoggerInterface::class),
            $this->createMock(QuoteFactory::class),
            $taxCalculation,
            $taxConfig,
            $this->createMock(BuckarooFee::class),
            $this->createMock(SoftwareData::class),
            $this->createMock(Factory::class),
            $this->createMock(PayReminderService::class),
            $this->createMock(\Magento\Quote\Model\ResourceModel\Quote::class)
        );

        $method = new \ReflectionMethod($handler, 'getShippingCostsLine');
        $method->setAccessible(true);
        $itemsTotalAmount = 0;
        $result = $method->invokeArgs($handler, [$orderMock, &$itemsTotalAmount, false]);

        $this->assertSame(
            self::SHIPPING_TAX_CLASS_ID,
            $rateRequest->getProductClassId(),
            'The shipping tax class must be applied to the rate request'
        );
        $this->assertNotEmpty($result['articles'] ?? [], 'A shipping cost article is expected');
    }
}
