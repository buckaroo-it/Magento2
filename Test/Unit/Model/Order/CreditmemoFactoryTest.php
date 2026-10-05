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

namespace Buckaroo\Magento2\Test\Unit\Model\Order;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Order\CreditmemoFactory;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Convert\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoValidator;
use Magento\Sales\Model\Order\Invoice;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Core `createByInvoice()` sets each item qty to min() of a float and the invoice item qty, which the
 * database returns as a string. When both are equal, PHP 8.4+ min() returns the string, and the
 * strict-typed QuantityValidator behind POST /V1/invoice/:id/refund throws a TypeError
 * (magento/magento2#40302). Observer extensions such as Blackfire restore the old behaviour, so this
 * test only goes red without the cast on a PHP 8.4+ build that has none loaded.
 */
class CreditmemoFactoryTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = CreditmemoFactory::class;

    public function setUp(): void
    {
        parent::setUp();

        $validator = $this->getFakeMock(CreditmemoValidator::class)->getMock();
        $validator->method('canRefundItem')->willReturn(true);
        $localeFormat = $this->getFakeMock(FormatInterface::class)->getMock();

        // The parent constructor resolves these through ObjectManager::getInstance().
        $appObjectManager = $this->getFakeMock(ObjectManagerInterface::class)->getMock();
        $appObjectManager->method('get')->willReturnCallback(
            fn (string $class) => $class === CreditmemoValidator::class ? $validator : $localeFormat
        );
        \Magento\Framework\App\ObjectManager::setInstance($appObjectManager);
    }

    public function tearDown(): void
    {
        $property = new \ReflectionProperty(\Magento\Framework\App\ObjectManager::class, '_instance');
        $property->setValue(null, null);

        parent::tearDown();
    }

    public function testCreateByInvoiceStoresTheFullInvoicedQtyAsAFloat(): void
    {
        $orderItem = $this->getFakeMock(Order\Item::class)
            ->onlyMethods(['getId', 'isDummy', 'getQtyToRefund'])
            ->getMock();
        $orderItem->method('getId')->willReturn(7);
        $orderItem->method('isDummy')->willReturn(false);
        $orderItem->method('getQtyToRefund')->willReturn(1.0);

        $invoiceItem = $this->getFakeMock(Invoice\Item::class)
            ->onlyMethods(['getOrderItem', 'getQty'])
            ->getMock();
        $invoiceItem->method('getOrderItem')->willReturn($orderItem);
        $invoiceItem->method('getQty')->willReturn('1.0000');

        $order = $this->getFakeMock(Order::class)
            ->onlyMethods(['getCreditmemosCollection', 'getStoreId'])
            ->getMock();
        $order->method('getCreditmemosCollection')->willReturn([]);
        $order->method('getStoreId')->willReturn(1);

        $invoice = $this->getFakeMock(Invoice::class)
            ->onlyMethods(['getOrder', 'getAllItems', 'getId'])
            ->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);
        $invoice->method('getId')->willReturn(194);

        $creditmemoItem = $this->getFakeMock(Creditmemo\Item::class)->onlyMethods([])->getMock();

        $addedItems = [];
        $creditmemo = $this->getFakeMock(Creditmemo::class)
            ->onlyMethods(['setInvoice', 'addItem', 'getAllItems', 'collectTotals'])
            ->getMock();
        $creditmemo->method('addItem')->willReturnCallback(
            function ($item) use (&$addedItems, $creditmemo) {
                $addedItems[] = $item;
                return $creditmemo;
            }
        );
        $creditmemo->method('getAllItems')->willReturnCallback(
            function () use (&$addedItems) {
                return $addedItems;
            }
        );

        $convertor = $this->getFakeMock(ConvertOrder::class)
            ->onlyMethods(['toCreditmemo', 'itemToCreditmemoItem'])
            ->getMock();
        $convertor->method('toCreditmemo')->willReturn($creditmemo);
        $convertor->method('itemToCreditmemoItem')->willReturn($creditmemoItem);

        $convertOrderFactory = $this->getFakeMock(OrderFactory::class)->onlyMethods(['create'])->getMock();
        $convertOrderFactory->method('create')->willReturn($convertor);

        $taxConfig = $this->getFakeMock(TaxConfig::class)->getMock();
        $taxConfig->method('displaySalesShippingInclTax')->willReturn(false);

        $factory = new CreditmemoFactory(
            $convertOrderFactory,
            $taxConfig,
            $this->getFakeMock(BuckarooLoggerInterface::class)->getMock(),
            $this->getFakeMock(Json::class)->getMock()
        );

        $result = $factory->createByInvoice($invoice);

        $this->assertSame([$creditmemoItem], $result->getAllItems());
        $this->assertSame(1.0, $creditmemoItem->getQty());
    }
}
