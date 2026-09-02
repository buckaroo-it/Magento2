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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;


use PHPUnit\Framework\Attributes\DataProvider;
use Buckaroo\Magento2\Gateway\Response\SkipPushHandler;

class SkipPushHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var SkipPushHandler
     */
    private $skipPushHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipPushHandler = new SkipPushHandler(
            $this->createMock(\Magento\Sales\Api\OrderPaymentRepositoryInterface::class)
        );
    }

    /**
     *
     * @param $skipPush
     *
     * @throws \Exception
     */
    #[DataProvider('skipPushDataProvider')]
    public function testHandle($skipPush): void
    {
        $this->orderPaymentMock
            ->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('skip_push')
            ->willReturn($skipPush);

        if ($skipPush > 0) {
            $this->orderPaymentMock
                ->expects($this->once())
                ->method('setAdditionalInformation')
                ->with('skip_push', $skipPush - 1);
        } else {
            $this->orderPaymentMock
                ->expects($this->never())
                ->method('setAdditionalInformation');
        }

        $this->skipPushHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }

    public static function skipPushDataProvider()
    {
        return [
            ['skipPush' => 1],
            ['skipPush' => 0],
            ['skipPush' => 2],
            ['skipPush' => -1],
            ['skipPush' => null],
        ];
    }
}
