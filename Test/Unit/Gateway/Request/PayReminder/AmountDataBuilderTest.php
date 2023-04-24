<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\PayReminder;

use Buckaroo\Magento2\Gateway\Request\PayReminder\AmountDataBuilder;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use PHPUnit\Framework\MockObject\MockObject;

class AmountDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|PayReminderService
     */
    private $payReminderServiceMock;

    /**
     * @var AmountDataBuilder
     */
    private $amountDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->payReminderServiceMock = $this->getMockBuilder(PayReminderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->amountDataBuilder = new AmountDataBuilder($this->payReminderServiceMock);
    }

    /**
     * @dataProvider buildDataProvider
     *
     * @param string $serviceAction
     * @param float $payRemainder
     * @param array $expectedResult
     */
    public function testBuild(string $serviceAction, float $payRemainder, array $expectedResult): void
    {
        $incrementId = '100000001';

        $this->orderMock->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($incrementId);

        $this->payReminderServiceMock->expects($this->once())
            ->method('getServiceAction')
            ->with($incrementId)
            ->willReturn($serviceAction);

        if ($serviceAction === 'payRemainder') {
            $this->payReminderServiceMock->expects($this->once())
                ->method('getPayRemainder')
                ->with($this->orderMock)
                ->willReturn($payRemainder);
        }

        $result = $this->amountDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            ['payRemainder', 123.45, ['amountDebit' => 123.45]],
            ['otherAction', 0, []],
        ];
    }
}
