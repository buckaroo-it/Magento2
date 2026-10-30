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

use Buckaroo\Magento2\Model\Order\CreditmemoFactory;

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

        $validator = $this->getFakeMock('Magento\Sales\Model\Order\CreditmemoValidator')->getMock();
        $validator->method('canRefundItem')->willReturn(true);
        $localeFormat = $this->getFakeMock('Magento\Framework\Locale\FormatInterface')->getMock();

        // The parent constructor resolves these through ObjectManager::getInstance().
        $appObjectManager = $this->getFakeMock('Magento\Framework\ObjectManagerInterface')->getMock();
        $appObjectManager->method('get')->willReturnCallback(
            fn (string $class) => str_ends_with($class, 'CreditmemoValidator') ? $validator : $localeFormat
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
        $orderItem = $this->getFakeMock('Magento\Sales\Model\Order\Item')
            ->onlyMethods(['getId', 'isDummy', 'getQtyToRefund'])
            ->getMock();
        $orderItem->method('getId')->willReturn(7);
        $orderItem->method('isDummy')->willReturn(false);
        $orderItem->method('getQtyToRefund')->willReturn(1.0);

        $invoiceItem = $this->getFakeMock('Magento\Sales\Model\Order\Invoice\Item')
            ->onlyMethods(['getOrderItem', 'getQty'])
            ->getMock();
        $invoiceItem->method('getOrderItem')->willReturn($orderItem);
        $invoiceItem->method('getQty')->willReturn('1.0000');

        $order = $this->getFakeMock('Magento\Sales\Model\Order')
            ->onlyMethods(['getCreditmemosCollection', 'getStoreId'])
            ->getMock();
        $order->method('getCreditmemosCollection')->willReturn([]);
        $order->method('getStoreId')->willReturn(1);

        $invoice = $this->getFakeMock('Magento\Sales\Model\Order\Invoice')
            ->onlyMethods(['getOrder', 'getAllItems', 'getId'])
            ->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->method('getAllItems')->willReturn([$invoiceItem]);
        $invoice->method('getId')->willReturn(194);

        $creditmemoItem = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo\Item')->onlyMethods([])->getMock();

        $addedItems = [];
        $creditmemo = $this->getFakeMock('Magento\Sales\Model\Order\Creditmemo')
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

        $convertor = $this->getFakeMock('Magento\Sales\Model\Convert\Order')
            ->onlyMethods(['toCreditmemo', 'itemToCreditmemoItem'])
            ->getMock();
        $convertor->method('toCreditmemo')->willReturn($creditmemo);
        $convertor->method('itemToCreditmemoItem')->willReturn($creditmemoItem);

        $convertOrderFactory = $this->getFakeMock('Magento\Sales\Model\Convert\OrderFactory')
            ->onlyMethods(['create'])
            ->getMock();
        $convertOrderFactory->method('create')->willReturn($convertor);

        $taxConfig = $this->getFakeMock('Magento\Tax\Model\Config')->getMock();
        $taxConfig->method('displaySalesShippingInclTax')->willReturn(false);

        $factory = new CreditmemoFactory(
            $convertOrderFactory,
            $taxConfig,
            $this->getFakeMock('Buckaroo\Magento2\Logging\BuckarooLoggerInterface')->getMock(),
            $this->getFakeMock('Magento\Framework\Serialize\Serializer\Json')->getMock()
        );

        $result = $factory->createByInvoice($invoice);

        $this->assertSame([$creditmemoItem], $result->getAllItems());
        $this->assertSame(1.0, $creditmemoItem->getQty());
    }
}
