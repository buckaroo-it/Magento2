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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Data\Order\OrderAdapter;
use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Service\Formatter\BirthDateFormatter;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractRecipientDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var AbstractRecipientDataBuilder
     */
    private $builder;

    /**
     * @var InfoInterface|MockObject
     */
    private $paymentMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new AbstractRecipientDataBuilder(
            new BirthDateFormatter($this->createMock(BuckarooLoggerInterface::class))
        );

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getFirstname')->willReturn('Albina');
        $addressMock->method('getLastname')->willReturn('Baraliu');

        $this->orderMock->method('getBillingAddress')->willReturn($addressMock);
    }

    /**
     * Regression for the merchant-reported crash:
     * "TypeError: date(): Argument #2 ($timestamp) must be of type ?int, false
     * given in AbstractRecipientDataBuilder.php:138".
     *
     * An order with no usable birthdate - the ?? fallback only caught null, so an
     * empty customer_dob reached strtotime() and came back false - must now build
     * a request instead of throwing.
     */
    public function testBuildFallsBackToThePlaceholderInsteadOfThrowing(): void
    {
        $cases = [
            'empty customer_dob (the reported crash)' => [null, ''],
            'whitespace customer_dob'                 => [null, '   '],
            'null customer_dob'                       => [null, null],
            'empty DoB field and empty order dob'     => ['', ''],
            'unparsable DoB field, no order dob'      => ['12/31/1990', null],
        ];

        foreach ($cases as $label => [$customerDoB, $orderDob]) {
            $result = $this->buildWith($customerDoB, $orderDob);

            $this->assertSame('01-01-1990', $result['recipient']['birthDate'], $label);
        }
    }

    /**
     * A usable birthdate must still come through untouched, in every separator
     * the checkout accepts.
     */
    public function testBuildKeepsAUsableBirthDate(): void
    {
        $cases = [
            'slashes' => '31/12/1990',
            'dashes'  => '31-12-1990',
            'dots'    => '31.12.1990',
        ];

        foreach ($cases as $label => $customerDoB) {
            $result = $this->buildWith($customerDoB, null);

            $this->assertSame('31-12-1990', $result['recipient']['birthDate'], $label);
        }
    }

    /**
     * Build a recipient request for a given checkout DoB / order DoB pair, using
     * fresh order and payment mocks so each case is isolated.
     *
     * @param string|null $customerDoB
     * @param string|null $orderDob
     *
     * @return array
     */
    private function buildWith(?string $customerDoB, ?string $orderDob): array
    {
        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getCustomerDob')->willReturn($orderDob);

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getFirstname')->willReturn('Albina');
        $addressMock->method('getLastname')->willReturn('Baraliu');
        $this->orderMock->method('getBillingAddress')->willReturn($addressMock);

        $this->paymentMock = $this->createMock(InfoInterface::class);
        $this->paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                ['customer_gender', null],
                ['customer_DoB', $customerDoB],
            ]);

        return $this->builder->build(['payment' => $this->getPaymentDOMock()]);
    }

    /**
     * The order's customer_dob is a datetime string, and it must be used when the
     * checkout DoB field was never filled in.
     */
    public function testBuildFallsBackToTheOrderBirthDate(): void
    {
        $this->orderMock->method('getCustomerDob')->willReturn('1985-06-15 00:00:00');
        $this->paymentMock = $this->createMock(InfoInterface::class);
        $this->paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                ['customer_gender', null],
                ['customer_DoB', null],
            ]);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertSame('15-06-1985', $result['recipient']['birthDate']);
    }

    /**
     * Build a payment data object whose payment info mock is controllable so we
     * can stub getAdditionalInformation for the birthdate lookups.
     *
     * @return PaymentDataObjectInterface|MockObject
     */
    protected function getPaymentDOMock()
    {
        $paymentDOMock = $this->createMock(PaymentDataObjectInterface::class);

        $orderAdapter = $this->createMock(OrderAdapter::class);
        $orderAdapter->method('getOrder')->willReturn($this->orderMock);

        $paymentDOMock->method('getOrder')->willReturn($orderAdapter);
        $paymentDOMock->method('getPayment')->willReturn($this->paymentMock);

        return $paymentDOMock;
    }
}
