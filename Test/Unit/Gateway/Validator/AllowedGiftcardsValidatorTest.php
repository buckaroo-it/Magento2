<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AllowedGiftcardsValidator;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\TestCase;

/**
 * The allowed giftcards are read from the store of the quote or order being paid.
 */
class AllowedGiftcardsValidatorTest extends TestCase
{
    public function testStoreComesFromTheQuoteInTheSubject(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(3);

        $this->assertValidatedForStore(['quote' => $quote], 3);
    }

    public function testStoreComesFromTheQuoteOfThePayment(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(2);
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getQuote')->willReturn($quote);

        $this->assertValidatedForStore(['payment' => $this->paymentDataObject($payment)], 2);
    }

    public function testStoreComesFromTheOrderOfThePayment(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(4);
        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertValidatedForStore(['payment' => $this->paymentDataObject($payment)], 4);
    }

    public function testWithoutQuoteOrPaymentTheAmbientStoreIsUsed(): void
    {
        $this->assertValidatedForStore([], null);
    }

    /**
     * @param object $payment
     *
     * @return PaymentDataObjectInterface
     */
    private function paymentDataObject($payment): PaymentDataObjectInterface
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        return $paymentDO;
    }

    /**
     * @param array    $subject
     * @param int|null $expectedStoreId
     */
    private function assertValidatedForStore(array $subject, ?int $expectedStoreId): void
    {
        $config = $this->createMock(GiftcardsConfig::class);
        $config->expects($this->once())->method('getAllowedGiftcards')
            ->with($expectedStoreId)
            ->willReturn('fashioncheque');

        $result = $this->createMock(ResultInterface::class);
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $resultFactory->method('create')
            ->with(['isValid' => true, 'failsDescription' => [], 'errorCodes' => []])
            ->willReturn($result);

        $validator = new AllowedGiftcardsValidator($resultFactory, $config);
        $this->assertSame($result, $validator->validate($subject));
    }
}
