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

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\App\Emulation;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Real Magento sales entities filled with data, and the collaborators an articles handler needs.
 *
 * The tax classes map to fixed rates: the payment fee class to 21% and the shipping class to 9%.
 */
trait ArticlesHandlerFixtures
{
    /**
     * @var array
     */
    private array $config = [];

    /**
     * @var bool
     */
    private bool $isPayRemainder = false;

    /**
     * @var float
     */
    private float $payRemainder = 0.0;

    /**
     * @var string
     */
    private string $edition = 'Community';

    /**
     * @var Quote|null
     */
    private $quote = null;

    /**
     * @var BuckarooLoggerInterface|null
     */
    private $handlerLogger = null;

    /**
     * @var ConfigProviderMethodFactory|null
     */
    private $methodFactory = null;

    /**
     * Constructor arguments shared by every articles handler.
     *
     * @param Emulation|null $emulation
     *
     * @return array
     */
    private function handlerArgs(?Emulation $emulation = null): array
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn($path) => $this->config[$path] ?? '1'
        );

        $taxCalculation = $this->createMock(Calculation::class);
        $taxCalculation->method('getRateRequest')->willReturnCallback(fn() => new DataObject());
        $taxCalculation->method('getRate')->willReturnCallback(
            fn($request) => [7 => 21.0, 8 => 9.0][$request->getProductClassId()] ?? 0.0
        );

        $taxConfig = $this->createMock(TaxConfig::class);
        $taxConfig->method('getShippingTaxClass')->willReturn(8);

        $fee = $this->createMock(BuckarooFee::class);
        $fee->method('getBuckarooFeeTaxClass')->willReturn(7);

        $metaData = $this->createMock(ProductMetadataInterface::class);
        $metaData->method('getEdition')->willReturnCallback(fn() => $this->edition);
        $softwareData = $this->createMock(SoftwareData::class);
        $softwareData->method('getProductMetaData')->willReturn($metaData);

        $payRemainderService = $this->createMock(PayReminderService::class);
        $payRemainderService->method('isPayRemainder')->willReturnCallback(fn() => $this->isPayRemainder);
        $payRemainderService->method('getPayRemainder')->willReturnCallback(fn() => $this->payRemainder);

        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturnCallback(fn() => $this->quote);

        $this->handlerLogger = $this->createMock(BuckarooLoggerInterface::class);
        $this->methodFactory = $this->createMock(ConfigProviderMethodFactory::class);

        return [
            $scopeConfig,
            $this->handlerLogger,
            $quoteFactory,
            $taxCalculation,
            $taxConfig,
            $fee,
            $softwareData,
            $this->methodFactory,
            $payRemainderService,
            $this->createMock(QuoteResource::class),
            $emulation,
        ];
    }

    /**
     * Prices on the catalog and on shipping exclude tax.
     *
     * @return void
     */
    private function pricesExcludeTax(): void
    {
        $this->config['tax/calculation/price_includes_tax'] = '0';
        $this->config['tax/calculation/shipping_includes_tax'] = '0';
    }

    /**
     * @param array $items
     *
     * @return void
     */
    private function cart(array $items): void
    {
        $quote = $this->entity(Quote::class, ['getAllItems']);
        $quote->method('getAllItems')->willReturn($items);
        $this->quote = $quote;
    }

    /**
     * @param string $class
     * @param array  $methods
     * @param array  $data
     *
     * @return mixed
     */
    private function entity(string $class, array $methods = [], array $data = [])
    {
        $entity = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
        $entity->setData($data);

        return $entity;
    }

    /**
     * @param array $data
     * @param array $items
     * @param array $invoices
     * @param bool  $canInvoice
     * @param bool  $hasCreditmemos
     *
     * @return Order
     */
    private function order(
        array $data,
        array $items = [],
        array $invoices = [],
        bool $canInvoice = false,
        bool $hasCreditmemos = false
    ) {
        $order = $this->entity(
            Order::class,
            [
                'getStore', 'getAllItems', 'getInvoiceCollection', 'canInvoice', 'hasCreditmemos',
                'getShippingAddress', 'getBillingAddress',
            ],
            $data + ['store_id' => 1, 'quote_id' => 55]
        );
        $order->method('getAllItems')->willReturn($items);
        $order->method('getInvoiceCollection')->willReturn($this->invoiceCollection($invoices));
        $order->method('canInvoice')->willReturn($canInvoice);
        $order->method('hasCreditmemos')->willReturn($hasCreditmemos);

        return $order;
    }

    /**
     * @param array $invoices
     *
     * @return object
     */
    private function invoiceCollection(array $invoices)
    {
        return new class($invoices) implements \IteratorAggregate, \Countable {
            /**
             * @var array
             */
            private array $invoices;

            /**
             * @param array $invoices
             */
            public function __construct(array $invoices)
            {
                $this->invoices = $invoices;
            }

            /**
             * @return \ArrayIterator
             */
            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator($this->invoices);
            }

            /**
             * @return int
             */
            public function count(): int
            {
                return count($this->invoices);
            }

            /**
             * @return mixed
             */
            public function getLastItem()
            {
                return end($this->invoices);
            }
        };
    }

    /**
     * @param array $data
     *
     * @return Order\Item
     */
    private function orderItem(array $data)
    {
        return $this->entity(Order\Item::class, [], $data + ['product_type' => 'simple']);
    }

    /**
     * @param array      $data
     * @param mixed|null $product
     *
     * @return Quote\Item
     */
    private function quoteItem(array $data, $product = null)
    {
        $data += ['product_type' => 'simple', 'weee_tax_applied_amount' => 0.0, 'discount_amount' => 0.0];
        if ($product === null) {
            $product = $this->createMock(\Magento\Catalog\Model\Product::class);
            $product->method('getTypeId')->willReturn($data['product_type']);
        }
        $data['product'] = $product;

        return $this->entity(Quote\Item::class, [], $data);
    }

    /**
     * @param array           $data
     * @param Order\Item|null $orderItem
     *
     * @return Invoice\Item
     */
    private function invoiceItem(array $data, $orderItem)
    {
        $item = $this->entity(Invoice\Item::class, ['getOrderItem'], $data);
        $item->method('getOrderItem')->willReturn($orderItem);

        return $item;
    }

    /**
     * @param array           $data
     * @param Order\Item|null $orderItem
     *
     * @return Creditmemo\Item
     */
    private function creditmemoItem(array $data, $orderItem)
    {
        $item = $this->entity(Creditmemo\Item::class, ['getOrderItem'], $data + ['weee_tax_applied_amount' => 0.0]);
        $item->method('getOrderItem')->willReturn($orderItem);

        return $item;
    }

    /**
     * @param array $data
     * @param array $items
     *
     * @return Invoice
     */
    private function invoice(array $data, array $items)
    {
        $invoice = $this->entity(
            Invoice::class,
            ['getAllItems', 'getStore', 'getShippingAddress', 'getBillingAddress'],
            $data
        );
        $invoice->method('getAllItems')->willReturn($items);

        return $invoice;
    }

    /**
     * @param array $data
     * @param array $items
     *
     * @return Creditmemo
     */
    private function creditmemo(array $data, array $items)
    {
        $creditmemo = $this->entity(
            Creditmemo::class,
            ['getAllItems', 'getStore', 'getShippingAddress', 'getBillingAddress'],
            $data
        );
        $creditmemo->method('getAllItems')->willReturn($items);

        return $creditmemo;
    }

    /**
     * @param Order|null      $order
     * @param Creditmemo|null $creditmemo
     * @param string          $method
     *
     * @return Payment
     */
    private function payment($order = null, $creditmemo = null, string $method = 'buckaroo_magento2_afterpay')
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getCreditmemo')->willReturn($creditmemo);
        $payment->method('getMethod')->willReturn($method);

        return $payment;
    }

    /**
     * An article line as the default handlers build it.
     *
     * @param string $identifier
     * @param string $description
     * @param float  $vat
     * @param int    $quantity
     * @param float  $price
     *
     * @return array
     */
    private function line(string $identifier, string $description, float $vat, int $quantity, float $price): array
    {
        return [
            'identifier' => $identifier,
            'description' => $description,
            'vatPercentage' => $vat,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }

    /**
     * @param array $line
     *
     * @return array
     */
    private function refund(array $line): array
    {
        return ['refundType' => 'Refund'] + $line;
    }
}
