<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Abstract base class for Gateway Request Builder tests
 */
abstract class AbstractRequestBuilderTest extends TestCase
{
    /**
     * Get a payment data object mock for tests
     * @return MockObject
     */
    protected function getPaymentDOMock()
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);
        $paymentMock = $this->createMock(InfoInterface::class);

        $paymentDOMock->method('getPayment')->willReturn($paymentMock);

        return $paymentDOMock;
    }
}
