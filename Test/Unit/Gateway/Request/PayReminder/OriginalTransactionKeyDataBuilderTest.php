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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request\PayReminder;

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

        $this->payReminderServiceMock = $this->getMockBuilder(PayReminderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->originalTransactionKeyDataBuilder = new OriginalTransactionKeyDataBuilder($this->payReminderServiceMock);
    }

    /**
     * @dataProvider buildDataProvider
     *
     * @param string $serviceAction
     * @param string $originalTransactionKey
     * @param array $expectedResult
     */
    public function testBuild(string $serviceAction, string $originalTransactionKey, array $expectedResult): void
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
                ->method('getOriginalTransactionKey')
                ->with($this->orderMock)
                ->willReturn($originalTransactionKey);
        }

        $result = $this->originalTransactionKeyDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function buildDataProvider(): array
    {
        return [
            [
                'payRemainder',
                '5EC466B0FFC745028BD74DFC9FBBFE38',
                ['originalTransactionKey' => '5EC466B0FFC745028BD74DFC9FBBFE38']
            ],
            ['otherAction', '', []],
        ];
    }
}
