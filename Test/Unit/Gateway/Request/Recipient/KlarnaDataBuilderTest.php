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
use Buckaroo\Magento2\Gateway\Request\Recipient\KlarnaDataBuilder;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;

class KlarnaDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var KlarnaDataBuilder
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

        $this->builder = new KlarnaDataBuilder();

        $addressMock = $this->createMock(Address::class);
        $addressMock->method('getFirstname')->willReturn('Albina');
        $addressMock->method('getLastname')->willReturn('Baraliu');

        $this->orderMock->method('getBillingAddress')->willReturn($addressMock);
        $this->orderMock->method('getCustomerDob')->willReturn('1990-01-01');
    }

    /**
     * Klarna (MoR) no longer collects a gender, so the mandatory gender
     * parameter must always be sent as "unknown" regardless of any legacy
     * customer_gender value that might still be present on the payment.
     *
     * @dataProvider customerGenderProvider
     *
     * @param string|null $customerGender
     */
    public function testGenderIsAlwaysUnknown(?string $customerGender): void
    {
        $this->paymentMock = $this->createMock(InfoInterface::class);
        $this->paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                ['customer_gender', $customerGender],
                ['customer_DoB', null],
            ]);

        $result = $this->builder->build(['payment' => $this->getPaymentDOMock()]);

        $this->assertArrayHasKey('recipient', $result);
        $this->assertArrayHasKey('recipient', $result['recipient']);
        $this->assertSame('unknown', $result['recipient']['recipient']['gender']);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public function customerGenderProvider(): array
    {
        return [
            'legacy male value' => ['1'],
            'legacy female value' => ['2'],
            'no gender supplied' => [null],
        ];
    }

    /**
     * Build a payment data object whose payment info mock is controllable so we
     * can stub getAdditionalInformation for the gender/birthdate lookups.
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
