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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\AdditionalInformation\OriginalTransactionKeyDataBuilder;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Test\Unit\Stubs\OrderStub;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OriginalTransactionKeyDataBuilderTest extends TestCase
{
    private const DATAREQUEST_KEY = 'DATAREQUEST_RESERVE_KEY_0000000001';
    private const PAY_KEY = 'PAY_TRANSACTION_KEY_00000000000001';

    /**
     * @var OriginalTransactionKeyDataBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new OriginalTransactionKeyDataBuilder(
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    /**
     * Build the payment data object the gateway passes to the builder.
     *
     * @param mixed $paymentMock
     * @param string|null $orderDataRequestKey key stored on the order (not the payment)
     */
    private function createPaymentDO($paymentMock, ?string $orderDataRequestKey = null)
    {
        $orderMock = $this->createMock(OrderStub::class);
        $orderMock->method('getBuckarooDatarequestKey')->willReturn($orderDataRequestKey);

        $orderAdapterMock = $this->createMock(OrderAdapter::class);
        $orderAdapterMock->method('getOrder')->willReturn($orderMock);
        $orderAdapterMock->method('getOrderIncrementId')->willReturn('100000001');

        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDOMock->method('getPayment')->willReturn($paymentMock);
        $paymentDOMock->method('getOrder')->willReturn($orderAdapterMock);

        return $paymentDOMock;
    }

    /**
     * @param string $method
     * @param array $additionalInformation
     * @param string|null $expectedKey
     * @param string|null $invoiceTransactionId
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(
        string $method,
        array $additionalInformation,
        ?string $expectedKey,
        ?string $invoiceTransactionId = null
    ): void {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn($method);
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(
                function ($key) use ($additionalInformation) {
                    return $additionalInformation[$key] ?? null;
                }
            );

        if ($invoiceTransactionId !== null) {
            $invoiceMock = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);
            $invoiceMock->method('getTransactionId')->willReturn($invoiceTransactionId);
            $creditmemoMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
            $creditmemoMock->method('getInvoice')->willReturn($invoiceMock);
            $paymentMock->method('getCreditmemo')->willReturn($creditmemoMock);
        }

        $result = $this->builder->build(['payment' => $this->createPaymentDO($paymentMock)]);

        $this->assertSame(['originalTransactionKey' => $expectedKey], $result);
    }

    /**
     * The DataRequest key often lives only on the order, not in the payment's additional
     * information. The corruption guards must still reject it there (BTI-1265).
     */
    public function testBuildRejectsCorruptKeysWhenDataRequestKeyIsOnlyOnTheOrder(): void
    {
        $additionalInformation = [
            'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
            'buckaroo_already_captured' => true,
            'buckaroo_original_transaction_key' => self::PAY_KEY,
        ];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(
                function ($key) use ($additionalInformation) {
                    return $additionalInformation[$key] ?? null;
                }
            );

        $invoiceMock = $this->createMock(\Magento\Sales\Model\Order\Invoice::class);
        $invoiceMock->method('getTransactionId')->willReturn(self::DATAREQUEST_KEY);
        $creditmemoMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $creditmemoMock->method('getInvoice')->willReturn($invoiceMock);
        $paymentMock->method('getCreditmemo')->willReturn($creditmemoMock);

        $result = $this->builder->build([
            'payment' => $this->createPaymentDO($paymentMock, self::DATAREQUEST_KEY),
        ]);

        $this->assertSame(['originalTransactionKey' => self::PAY_KEY], $result);
    }

    /**
     * The DataRequest key comparison must not be defeated by casing or padding.
     */
    public function testBuildRejectsDataRequestKeyRegardlessOfCaseAndWhitespace(): void
    {
        $additionalInformation = [
            'buckaroo_datarequest_key' => ' ' . strtolower(self::DATAREQUEST_KEY) . ' ',
            'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
            'buckaroo_already_captured' => true,
            'buckaroo_original_transaction_key' => self::PAY_KEY,
        ];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(
                function ($key) use ($additionalInformation) {
                    return $additionalInformation[$key] ?? null;
                }
            );

        $result = $this->builder->build(['payment' => $this->createPaymentDO($paymentMock)]);

        $this->assertSame(['originalTransactionKey' => self::PAY_KEY], $result);
    }

    /**
     * A payment implementation without getCreditmemo() must fall back, not fatal.
     */
    public function testBuildFallsBackWhenPaymentIsNotAnOrderPayment(): void
    {
        $additionalInformation = [
            'buckaroo_capture_transaction_key' => self::PAY_KEY,
            'buckaroo_already_captured' => true,
        ];

        // InfoInterface does not declare getMethod(); PaymentWithMethodStub adds it so the
        // double matches what the gateway actually passes while deliberately NOT being an
        // Order\Payment. (MockBuilder::addMethods() was removed in PHPUnit 12.)
        $paymentMock = $this->createMock(PaymentWithMethodStub::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(
                function ($key = null) use ($additionalInformation) {
                    return $additionalInformation[$key] ?? null;
                }
            );

        $result = $this->builder->build(['payment' => $this->createPaymentDO($paymentMock)]);

        $this->assertSame(['originalTransactionKey' => self::PAY_KEY], $result);
    }

    /**
     * Adjustment-only credit memos have no invoice; the capture key must be used instead.
     */
    public function testBuildFallsBackWhenCreditMemoHasNoInvoice(): void
    {
        $additionalInformation = [
            'buckaroo_capture_transaction_key' => self::PAY_KEY,
            'buckaroo_already_captured' => true,
        ];

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getMethod')->willReturn('buckaroo_magento2_klarna');
        $paymentMock->method('getAdditionalInformation')
            ->willReturnCallback(
                function ($key) use ($additionalInformation) {
                    return $additionalInformation[$key] ?? null;
                }
            );

        $creditmemoMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $creditmemoMock->method('getInvoice')->willReturn(null);
        $paymentMock->method('getCreditmemo')->willReturn($creditmemoMock);

        $result = $this->builder->build(['payment' => $this->createPaymentDO($paymentMock)]);

        $this->assertSame(['originalTransactionKey' => self::PAY_KEY], $result);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            'klarna prefers the credit memo invoice transaction id (per-invoice refund target)' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_capture_transaction_key' => 'STALE_FIRST_CAPTURE_KEY_0000000001',
                    'buckaroo_already_captured' => true,
                    'buckaroo_original_transaction_key' => self::DATAREQUEST_KEY,
                ],
                self::PAY_KEY,
                self::PAY_KEY,
            ],
            'klarna ignores invoice transaction id equal to datarequest key' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
                    'buckaroo_capture_transaction_key' => self::PAY_KEY,
                    'buckaroo_already_captured' => true,
                ],
                self::PAY_KEY,
                self::DATAREQUEST_KEY,
            ],
            'non-klarna methods ignore the invoice transaction id' => [
                'buckaroo_magento2_ideal',
                [
                    'buckaroo_original_transaction_key' => self::PAY_KEY,
                ],
                self::PAY_KEY,
                'SOME_OTHER_INVOICE_TRANSACTION_ID1',
            ],
            'klarna prefers capture key when already captured' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_capture_transaction_key' => self::PAY_KEY,
                    'buckaroo_already_captured' => true,
                    'buckaroo_original_transaction_key' => self::DATAREQUEST_KEY,
                ],
                self::PAY_KEY,
            ],
            'klarna ignores capture key equal to datarequest key (BTI-1265)' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
                    'buckaroo_already_captured' => true,
                    'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
                    'buckaroo_original_transaction_key' => self::PAY_KEY,
                ],
                self::PAY_KEY,
            ],
            'klarnakp ignores capture key equal to datarequest key' => [
                'buckaroo_magento2_klarnakp',
                [
                    'buckaroo_capture_transaction_key' => self::DATAREQUEST_KEY,
                    'buckaroo_already_captured' => true,
                    'buckaroo_datarequest_key' => self::DATAREQUEST_KEY,
                    'buckaroo_original_transaction_key' => self::PAY_KEY,
                ],
                self::PAY_KEY,
            ],
            'klarna falls back to original key without capture' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_original_transaction_key' => self::PAY_KEY,
                ],
                self::PAY_KEY,
            ],
            'klarna ignores capture key without already_captured flag' => [
                'buckaroo_magento2_klarna',
                [
                    'buckaroo_capture_transaction_key' => self::PAY_KEY,
                    'buckaroo_original_transaction_key' => self::DATAREQUEST_KEY,
                ],
                self::DATAREQUEST_KEY,
            ],
            'actual payment transaction key has highest precedence' => [
                'buckaroo_magento2_payperemail',
                [
                    'buckaroo_actual_payment_transaction_key' => self::PAY_KEY,
                    'buckaroo_original_transaction_key' => self::DATAREQUEST_KEY,
                ],
                self::PAY_KEY,
            ],
            'other methods use original key' => [
                'buckaroo_magento2_ideal',
                [
                    'buckaroo_original_transaction_key' => self::PAY_KEY,
                ],
                self::PAY_KEY,
            ],
        ];
    }
}
