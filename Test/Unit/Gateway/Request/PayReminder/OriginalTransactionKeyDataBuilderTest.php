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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\PayReminder;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Request\PayReminder\OriginalTransactionKeyDataBuilder;
use Buckaroo\Magento2\Service\PayReminderService;
use Buckaroo\Magento2\Test\Unit\Gateway\Request\AbstractDataBuilderTest;
use PHPUnit\Framework\MockObject\MockObject;

class OriginalTransactionKeyDataBuilderTest extends AbstractDataBuilderTest
{
    /**
     * @var MockObject|PayReminderService
     */
    private $payReminderServiceMock;

    /**
     * @var OriginalTransactionKeyDataBuilder
     */
    private $originalTransactionKeyDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->payReminderServiceMock = $this->createMock(PayReminderService::class);

        $this->originalTransactionKeyDataBuilder = new OriginalTransactionKeyDataBuilder($this->payReminderServiceMock);
    }

    /**
     *
     * @param string $serviceAction
     * @param string $originalTransactionKey
     * @param array  $expectedResult
     */
    #[DataProvider('buildDataProvider')]
    public function testBuild(string $serviceAction, string $originalTransactionKey, array $expectedResult): void
    {
        $incrementId = '100000001';

        $this->orderMock->method('getIncrementId')
            ->willReturn($incrementId);

        $this->payReminderServiceMock->method('getServiceAction')
            ->with($incrementId)
            ->willReturn($serviceAction);

        if ($expectedResult !== []) {
            $this->payReminderServiceMock->method('getOriginalTransactionKey')
                ->with($this->orderMock)
                ->willReturn($originalTransactionKey);
        }

        $result = $this->originalTransactionKeyDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            [
                'payRemainder',
                '5EC466B0FFC745028BD74DFC9FBBFE38',
                ['originalTransactionKey' => '5EC466B0FFC745028BD74DFC9FBBFE38']
            ],
            [
                'payRemainderEncrypted',
                '5EC466B0FFC745028BD74DFC9FBBFE38',
                ['originalTransactionKey' => '5EC466B0FFC745028BD74DFC9FBBFE38']
            ],
            [
                'payRemainderWithToken',
                '5EC466B0FFC745028BD74DFC9FBBFE38',
                ['originalTransactionKey' => '5EC466B0FFC745028BD74DFC9FBBFE38']
            ],
            ['payWithToken', '', []],
            ['otherAction', '', []],
        ];
    }
}
