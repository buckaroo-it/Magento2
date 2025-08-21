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

        $this->payReminderServiceMock = $this->createMock(PayReminderService::class);

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

        $this->orderMock->method('getIncrementId')
            ->willReturn($incrementId);

        $this->payReminderServiceMock->method('getServiceAction')
            ->with($incrementId)
            ->willReturn($serviceAction);

        if ($serviceAction === 'payRemainder') {
            $this->payReminderServiceMock->method('getPayRemainder')
                ->with($this->orderMock)
                ->willReturn($payRemainder);
        }

        $result = $this->amountDataBuilder->build(['payment' => $this->getPaymentDOMock()]);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public static function buildDataProvider(): array
    {
        return [
            ['payRemainder', 123.45, ['amountDebit' => 123.45]],
            ['otherAction', 0, []],
        ];
    }
}
