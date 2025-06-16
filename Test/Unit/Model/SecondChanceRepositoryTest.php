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

namespace Buckaroo\Magento2\Test\Unit\Model;

use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Model\SecondChanceFactory;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance as ResourceSecondChance;
use Buckaroo\Magento2\Model\ResourceModel\SecondChance\CollectionFactory as SecondChanceCollectionFactory;
use Buckaroo\Magento2\Api\Data\SecondChanceInterfaceFactory;
use Buckaroo\Magento2\Api\Data\SecondChanceSearchResultsInterfaceFactory;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as ConfigProvider;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreateService;
use Buckaroo\Magento2\Logging\Log;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Item;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Mail\TransportInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondChanceRepositoryTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = SecondChanceRepository::class;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $configProvider;
    
    /** @var Log|\PHPUnit\Framework\MockObject\MockObject */
    private $logging;
    
    /** @var OrderFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $orderFactory;
    
    /** @var QuoteFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $quoteFactory;
    
    /** @var Random|\PHPUnit\Framework\MockObject\MockObject */
    private $mathRandom;
    
    /** @var DateTime|\PHPUnit\Framework\MockObject\MockObject */
    private $dateTime;
    
    /** @var StockRegistryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $stockRegistry;
    
    /** @var TransportBuilder|\PHPUnit\Framework\MockObject\MockObject */
    private $transportBuilder;
    
    /** @var QuoteRecreateService|\PHPUnit\Framework\MockObject\MockObject */
    private $quoteRecreate;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->configProvider = $this->getFakeMock(ConfigProvider::class)->getMock();
        $this->logging = $this->getFakeMock(Log::class)->getMock();
        $this->orderFactory = $this->getFakeMock(OrderFactory::class)->getMock();
        $this->quoteFactory = $this->getFakeMock(QuoteFactory::class)->getMock();
        $this->mathRandom = $this->getFakeMock(Random::class)->getMock();
        $this->dateTime = $this->getFakeMock(DateTime::class)->getMock();
        $this->stockRegistry = $this->getFakeMock(StockRegistryInterface::class)->getMock();
        $this->transportBuilder = $this->getFakeMock(TransportBuilder::class)->getMock();
        $this->quoteRecreate = $this->getFakeMock(QuoteRecreateService::class)->getMock();
    }

    /**
     * @return array
     */
    public function createSecondChanceProvider()
    {
        return [
            'second chance enabled' => [
                true,
                'buckaroo_magento2_ideal',
                1
            ],
            'second chance disabled' => [
                false,
                'buckaroo_magento2_ideal',
                0
            ],
            'non-buckaroo payment method' => [
                true,
                'checkmo',
                0
            ],
        ];
    }

    /**
     * @param bool $secondChanceEnabled
     * @param string $paymentMethod
     * @param int $expectedCalls
     * 
     * @dataProvider createSecondChanceProvider
     */
    public function testCreateSecondChance($secondChanceEnabled, $paymentMethod, $expectedCalls)
    {
        $store = $this->getFakeMock(Store::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        $payment = $this->getFakeMock(Payment::class)->getMock();
        
        $order->expects($this->once())->method('getStore')->willReturn($store);
        $order->expects($this->any())->method('getPayment')->willReturn($payment);
        $payment->expects($this->any())->method('getMethod')->willReturn($paymentMethod);
        
        $this->configProvider->expects($this->once())
            ->method('isSecondChanceEnabled')
            ->with($store)
            ->willReturn($secondChanceEnabled);

        $secondChanceData = $this->getFakeMock(\Buckaroo\Magento2\Api\Data\SecondChanceInterface::class)->getMock();
        $secondChanceFactory = $this->getFakeMock(SecondChanceInterfaceFactory::class)->getMock();
        
        if ($expectedCalls > 0) {
            $this->mathRandom->expects($this->once())
                ->method('getRandomString')
                ->with(32)
                ->willReturn('random_token_string');
                
            $this->dateTime->expects($this->once())
                ->method('gmtDate')
                ->willReturn('2023-01-01 12:00:00');
                
            $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
            $order->expects($this->once())->method('getQuoteId')->willReturn(123);
            $order->expects($this->once())->method('getStoreId')->willReturn(1);
            $order->expects($this->once())->method('getCustomerEmail')->willReturn('test@example.com');
            
            $secondChanceFactory->expects($this->once())
                ->method('create')
                ->willReturn($secondChanceData);
                
            $secondChanceData->expects($this->once())->method('setOrderId')->with('000000001');
            $secondChanceData->expects($this->once())->method('setQuoteId')->with(123);
            $secondChanceData->expects($this->once())->method('setStoreId')->with(1);
            $secondChanceData->expects($this->once())->method('setCustomerEmail')->with('test@example.com');
            $secondChanceData->expects($this->once())->method('setToken')->with('random_token_string');
            $secondChanceData->expects($this->once())->method('setStatus')->with('pending');
            $secondChanceData->expects($this->once())->method('setStep')->with(1);
            $secondChanceData->expects($this->once())->method('setCreatedAt')->with('2023-01-01 12:00:00');
        }

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'dataSecondChanceFactory' => $secondChanceFactory,
            'mathRandom' => $this->mathRandom,
            'dateTime' => $this->dateTime,
        ]);

        $this->invokeArgs('createSecondChance', [$order], $instance);
    }

    /**
     * @return array
     */
    public function checkOrderProductsIsInStockProvider()
    {
        return [
            'all products in stock' => [
                [
                    ['product_id' => 1, 'qty_ordered' => 2, 'product_type' => 'simple'],
                    ['product_id' => 2, 'qty_ordered' => 1, 'product_type' => 'simple'],
                ],
                [
                    1 => ['is_in_stock' => true, 'qty' => 10],
                    2 => ['is_in_stock' => true, 'qty' => 5],
                ],
                true
            ],
            'one product out of stock' => [
                [
                    ['product_id' => 1, 'qty_ordered' => 2, 'product_type' => 'simple'],
                    ['product_id' => 2, 'qty_ordered' => 1, 'product_type' => 'simple'],
                ],
                [
                    1 => ['is_in_stock' => true, 'qty' => 10],
                    2 => ['is_in_stock' => false, 'qty' => 0],
                ],
                false
            ],
            'insufficient quantity' => [
                [
                    ['product_id' => 1, 'qty_ordered' => 15, 'product_type' => 'simple'],
                ],
                [
                    1 => ['is_in_stock' => true, 'qty' => 10],
                ],
                false
            ],
            'non-simple products ignored' => [
                [
                    ['product_id' => 1, 'qty_ordered' => 2, 'product_type' => 'configurable'],
                    ['product_id' => 2, 'qty_ordered' => 1, 'product_type' => 'simple'],
                ],
                [
                    2 => ['is_in_stock' => true, 'qty' => 5],
                ],
                true
            ],
        ];
    }

    /**
     * @param array $orderItems
     * @param array $stockData
     * @param bool $expectedResult
     * 
     * @dataProvider checkOrderProductsIsInStockProvider
     */
    public function testCheckOrderProductsIsInStock($orderItems, $stockData, $expectedResult)
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        
        $items = [];
        foreach ($orderItems as $itemData) {
            $item = $this->getFakeMock(Item::class)->getMock();
            $item->expects($this->any())->method('getProductId')->willReturn($itemData['product_id']);
            $item->expects($this->any())->method('getQtyOrdered')->willReturn($itemData['qty_ordered']);
            $item->expects($this->any())->method('getProductType')->willReturn($itemData['product_type']);
            $items[] = $item;
        }
        
        $order->expects($this->once())->method('getAllItems')->willReturn($items);
        $order->expects($this->any())->method('getStore')->willReturn($store);
        $store->expects($this->any())->method('getWebsiteId')->willReturn(1);
        
        $stockItemCalls = [];
        foreach ($stockData as $productId => $stock) {
            $stockItem = $this->getFakeMock(StockItemInterface::class)->getMock();
            $stockItem->expects($this->any())->method('getIsInStock')->willReturn($stock['is_in_stock']);
            $stockItem->expects($this->any())->method('getQty')->willReturn($stock['qty']);
            $stockItemCalls[] = [$productId, 1, $stockItem];
        }
        
        $this->stockRegistry->expects($this->exactly(count($stockItemCalls)))
            ->method('getStockItem')
            ->willReturnMap($stockItemCalls);

        $instance = $this->getInstance(['stockRegistry' => $this->stockRegistry]);
        $result = $this->invokeArgs('checkOrderProductsIsInStock', [$order], $instance);
        
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function sendMailProvider()
    {
        return [
            'step 1 email' => [1, 'buckaroo_second_chance_first'],
            'step 2 email' => [2, 'buckaroo_second_chance_second'],
        ];
    }

    /**
     * @param int $step
     * @param string $expectedTemplate
     * 
     * @dataProvider sendMailProvider
     */
    public function testSendMail($step, $expectedTemplate)
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $store = $this->getFakeMock(Store::class)->getMock();
        $secondChance = $this->getFakeMock(\Buckaroo\Magento2\Model\SecondChance::class)->getMock();
        $transport = $this->getFakeMock(TransportInterface::class)->getMock();
        $inlineTranslation = $this->getFakeMock(StateInterface::class)->getMock();
        $addressRenderer = $this->getFakeMock(Renderer::class)->getMock();
        $paymentHelper = $this->getFakeMock(PaymentHelper::class)->getMock();
        
        $order->expects($this->any())->method('getStore')->willReturn($store);
        $order->expects($this->any())->method('getIncrementId')->willReturn('000000001');
        $order->expects($this->any())->method('getCustomerEmail')->willReturn('test@example.com');
        $order->expects($this->any())->method('getCustomerName')->willReturn('John Doe');
        
        $secondChance->expects($this->once())->method('getToken')->willReturn('test_token');
        
        $store->expects($this->once())
            ->method('getUrl')
            ->with('buckaroo/secondchance', ['token' => 'test_token'])
            ->willReturn('http://example.com/buckaroo/secondchance?token=test_token');
        $store->expects($this->once())->method('getId')->willReturn(1);
        
        $this->configProvider->expects($this->once())
            ->method('getSecondChanceEmailTemplate')
            ->with($step, $store)
            ->willReturn($expectedTemplate);
            
        $this->configProvider->expects($this->once())
            ->method('getSecondChanceSenderName')
            ->with($store)
            ->willReturn('Store Owner');
            
        $this->configProvider->expects($this->once())
            ->method('getSecondChanceSenderEmail')
            ->with($store)
            ->willReturn('store@example.com');
        
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateIdentifier')
            ->with($expectedTemplate)
            ->willReturnSelf();
            
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateOptions')
            ->willReturnSelf();
            
        $this->transportBuilder->expects($this->once())
            ->method('setTemplateVars')
            ->willReturnSelf();
            
        $this->transportBuilder->expects($this->once())
            ->method('setFrom')
            ->willReturnSelf();
            
        $this->transportBuilder->expects($this->once())
            ->method('addTo')
            ->with('test@example.com', 'John Doe')
            ->willReturnSelf();
            
        $this->transportBuilder->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);
            
        $transport->expects($this->once())->method('sendMessage');
        
        $inlineTranslation->expects($this->once())->method('suspend');
        $inlineTranslation->expects($this->once())->method('resume');
        
        $this->logging->expects($this->once())
            ->method('addDebug')
            ->with($this->stringContains('SecondChance email sent successfully'));

        $instance = $this->getInstance([
            'configProvider' => $this->configProvider,
            'transportBuilder' => $this->transportBuilder,
            'inlineTranslation' => $inlineTranslation,
            'logging' => $this->logging,
            'addressRenderer' => $addressRenderer,
            'paymentHelper' => $paymentHelper,
        ]);

        $this->invokeArgs('sendMail', [$order, $secondChance, $step], $instance);
    }

    public function testGetSecondChanceByToken()
    {
        $token = 'test_token';
        $orderId = '000000001';
        
        $collection = $this->getFakeMock(\Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection::class)->getMock();
        $collectionFactory = $this->getFakeMock(SecondChanceCollectionFactory::class)->getMock();
        $secondChance = $this->getFakeMock(\Buckaroo\Magento2\Model\SecondChance::class)->getMock();
        $order = $this->getFakeMock(Order::class)->getMock();
        
        $collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);
            
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('token', $token)
            ->willReturnSelf();
            
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('status', ['neq' => 'completed'])
            ->willReturnSelf();
            
        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($secondChance);
            
        $secondChance->expects($this->once())->method('getId')->willReturn(1);
        $secondChance->expects($this->once())->method('getOrderId')->willReturn($orderId);
        $secondChance->expects($this->once())->method('setStatus')->with('clicked');
        $secondChance->expects($this->once())->method('getDataModel')->willReturn($secondChance);
        
        $this->orderFactory->expects($this->once())
            ->method('create')
            ->willReturn($order);
            
        $order->expects($this->once())
            ->method('loadByIncrementId')
            ->with($orderId)
            ->willReturnSelf();
            
        $order->expects($this->once())->method('getId')->willReturn(123);
        
        $this->quoteRecreate->expects($this->once())
            ->method('duplicate')
            ->with($order);

        $resource = $this->getFakeMock(ResourceSecondChance::class)->getMock();
        $resource->expects($this->once())->method('save')->with($secondChance);

        $instance = $this->getInstance([
            'secondChanceCollectionFactory' => $collectionFactory,
            'orderFactory' => $this->orderFactory,
            'quoteRecreate' => $this->quoteRecreate,
            'resource' => $resource,
        ]);

        $result = $this->invokeArgs('getSecondChanceByToken', [$token], $instance);
        $this->assertEquals($secondChance, $result);
    }

    public function testGetSecondChanceByTokenInvalidToken()
    {
        $token = 'invalid_token';
        
        $collection = $this->getFakeMock(\Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection::class)->getMock();
        $collectionFactory = $this->getFakeMock(SecondChanceCollectionFactory::class)->getMock();
        $secondChance = $this->getFakeMock(\Buckaroo\Magento2\Model\SecondChance::class)->getMock();
        
        $collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);
            
        $collection->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->willReturnSelf();
            
        $collection->expects($this->once())
            ->method('getFirstItem')
            ->willReturn($secondChance);
            
        $secondChance->expects($this->once())->method('getId')->willReturn(null);

        $instance = $this->getInstance([
            'secondChanceCollectionFactory' => $collectionFactory,
        ]);

        $this->expectException(\Magento\Framework\Exception\NoSuchEntityException::class);
        $this->expectExceptionMessage('Invalid token.');
        
        $this->invokeArgs('getSecondChanceByToken', [$token], $instance);
    }

    public function testCheckForMultipleEmail()
    {
        $order = $this->getFakeMock(Order::class)->getMock();
        $collection = $this->getFakeMock(\Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection::class)->getMock();
        $collectionFactory = $this->getFakeMock(SecondChanceCollectionFactory::class)->getMock();
        
        $order->expects($this->once())->method('getCustomerEmail')->willReturn('test@example.com');
        $order->expects($this->once())->method('getStoreId')->willReturn(1);
        $order->expects($this->once())->method('getIncrementId')->willReturn('000000001');
        
        $collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);
            
        $collection->expects($this->exactly(4))
            ->method('addFieldToFilter')
            ->willReturnSelf();
            
        $collection->expects($this->once())
            ->method('getSize')
            ->willReturn(0);

        $instance = $this->getInstance([
            'secondChanceCollectionFactory' => $collectionFactory,
        ]);

        $result = $this->invokeArgs('checkForMultipleEmail', [$order, true], $instance);
        $this->assertTrue($result);
    }
}