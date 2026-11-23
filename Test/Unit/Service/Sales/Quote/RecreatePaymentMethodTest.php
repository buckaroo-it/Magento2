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


namespace Buckaroo\Magento2\Test\Unit\Service\Sales\Quote;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\Sales\Quote\Recreate;
use Magento\Framework\DataObject;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The old payment method is only carried over to the recreated quote when it is available for it.
 */
class RecreatePaymentMethodTest extends TestCase
{
    /** @var PaymentHelper|MockObject */
    private $paymentHelper;

    /** @var Recreate */
    private $recreate;

    protected function setUp(): void
    {
        $this->paymentHelper = $this->createMock(PaymentHelper::class);

        $reflection = new \ReflectionClass(Recreate::class);
        $this->recreate = $reflection->newInstanceWithoutConstructor();
        foreach (['paymentHelper' => $this->paymentHelper, 'logger' => $this->createMock(Log::class)] as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($this->recreate, $value);
        }
    }

    /**
     * @return Quote|MockObject
     */
    private function quote(DataObject $payment, ?string $countryId = 'NL', ?int $storeId = 2)
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getBillingAddress')->willReturn(new DataObject(['country_id' => $countryId]));
        $quote->method('getStoreId')->willReturn($storeId);
        return $quote;
    }

    private function merge(Quote $oldQuote, Quote $newQuote): void
    {
        $method = new \ReflectionMethod(Recreate::class, 'additionalMerge');
        $method->setAccessible(true);
        $method->invoke($this->recreate, $oldQuote, $newQuote);
    }

    private function payment(array $data = []): DataObject
    {
        return new class($data) extends DataObject {
            /**
             * Same contract as the payment info's setAdditionalInformation($key, $value)
             */
            public function setAdditionalInformation($key, $value = null)
            {
                $info = (array)$this->getData('additional_information');
                $info[$key] = $value;
                return $this->setData('additional_information', $info);
            }
        };
    }

    private function oldPayment(): DataObject
    {
        return $this->payment([
            'method' => 'buckaroo_magento2_ideal',
            'additional_information' => ['issuer' => 'INGBNL2A'],
        ]);
    }

    private function methodAvailable(bool $available): void
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('isAvailable')->willReturn($available);
        $this->paymentHelper->method('getMethodInstance')->with('buckaroo_magento2_ideal')->willReturn($method);
    }

    public function testAvailableMethodIsCopiedWithAdditionalInformation(): void
    {
        $this->methodAvailable(true);
        $newPayment = $this->payment();
        $newQuote = $this->quote($newPayment);

        $this->merge($this->quote($this->oldPayment()), $newQuote);

        $this->assertSame('buckaroo_magento2_ideal', $newPayment->getMethod());
        $this->assertSame(['issuer' => 'INGBNL2A'], $newPayment->getData('additional_information'));
    }

    public function testUnavailableMethodIsNotSet(): void
    {
        $this->methodAvailable(false);
        $newPayment = $this->payment();

        $this->merge($this->quote($this->oldPayment()), $this->quote($newPayment));

        $this->assertNull($newPayment->getMethod());
    }

    public function testMethodIsNotSetWhenAvailabilityCannotBeChecked(): void
    {
        $this->paymentHelper->expects($this->never())->method('getMethodInstance');
        $newPayment = $this->payment();

        $this->merge($this->quote($this->oldPayment()), $this->quote($newPayment, null));

        $this->assertNull($newPayment->getMethod());
    }

    public function testMethodIsNotSetWhenAvailabilityCheckFails(): void
    {
        $this->paymentHelper->method('getMethodInstance')->willThrowException(new \RuntimeException('no method'));
        $newPayment = $this->payment();

        $this->merge($this->quote($this->oldPayment()), $this->quote($newPayment));

        $this->assertNull($newPayment->getMethod());
    }
}
