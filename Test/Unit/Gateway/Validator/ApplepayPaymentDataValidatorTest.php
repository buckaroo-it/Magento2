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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\ApplepayPaymentDataValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An inline Apple Pay order is only placed with the Apple Pay payment data; choosing the method on the
 * quote and redirect mode are not affected.
 */
class ApplepayPaymentDataValidatorTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = ApplepayPaymentDataValidator::class;

    /**
     * @return array<string, array{string, string, string, mixed, bool}>
     */
    public static function paymentProvider(): array
    {
        return [
            'order, inline, no data'          => ['order', Applepay::CODE, '0', null, false],
            'order, inline, empty data'       => ['order', Applepay::CODE, '0', '  ', false],
            'order, inline, with data'        => ['order', Applepay::CODE, '0', '{"paymentData":{}}', true],
            'order, redirect, no data'        => ['order', Applepay::CODE, '1', null, true],
            'quote, inline, no data'          => ['quote', Applepay::CODE, '0', null, true],
            'order, other method, no data'    => ['order', 'buckaroo_magento2_ideal', '0', null, true],
        ];
    }

    /**
     * @param string $paymentType
     * @param string $method
     * @param string $integrationMode
     * @param mixed  $paymentData
     * @param bool   $isValid
     */
    #[DataProvider('paymentProvider')]
    public function testOrderNeedsApplePayDataInInlineMode(
        string $paymentType,
        string $method,
        string $integrationMode,
        $paymentData,
        bool $isValid
    ): void {
        $created = [];
        $resultFactory = $this->getFakeMock(ResultInterfaceFactory::class)->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturnCallback(function ($data) use (&$created) {
            $created[] = $data['isValid'];
            return $this->getFakeMock(ResultInterface::class)->getMock();
        });

        $config = $this->getFakeMock(Applepay::class)->onlyMethods(['getIntegrationMode'])->getMock();
        $config->method('getIntegrationMode')->willReturn($integrationMode);

        $paymentClass = $paymentType === 'order' ? OrderPayment::class : QuotePayment::class;
        $payment = $this->getFakeMock($paymentClass)->onlyMethods(['getMethod', 'getAdditionalInformation'])->getMock();
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getAdditionalInformation')->willReturnMap([['applepayTransaction', $paymentData]]);

        (new ApplepayPaymentDataValidator($resultFactory, $config))->validate(['payment' => $payment, 'storeId' => 1]);

        $this->assertSame([$isValid], $created);
    }

    /**
     * Integration mode is set per store view; an admin or REST order does not run in its own store.
     */
    public function testChecksTheIntegrationModeOfTheOrdersStore(): void
    {
        $resultFactory = $this->getFakeMock(ResultInterfaceFactory::class)->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturn($this->getFakeMock(ResultInterface::class)->getMock());

        $config = $this->getFakeMock(Applepay::class)->onlyMethods(['getIntegrationMode'])->getMock();
        $config->expects($this->once())->method('getIntegrationMode')->with(3)->willReturn('1');

        $order = $this->getFakeMock(Order::class)->onlyMethods(['getStoreId'])->getMock();
        $order->method('getStoreId')->willReturn('3');
        $payment = $this->getFakeMock(OrderPayment::class)->onlyMethods(['getMethod', 'getOrder'])->getMock();
        $payment->method('getMethod')->willReturn(Applepay::CODE);
        $payment->method('getOrder')->willReturn($order);

        (new ApplepayPaymentDataValidator($resultFactory, $config))->validate(['payment' => $payment]);
    }
}
