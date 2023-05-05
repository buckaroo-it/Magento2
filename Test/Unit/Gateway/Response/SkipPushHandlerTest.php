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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;

use Buckaroo\Magento2\Gateway\Response\SkipPushHandler;

class SkipPushHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var SkipPushHandler
     */
    private SkipPushHandler $skipPushHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipPushHandler = new SkipPushHandler();
    }

    /**
     * @dataProvider skipPushDataProvider
     *
     * @param $skipPush
     * @return void
     * @throws \Exception
     */
    public function testHandle($skipPush): void
    {
        $this->orderPaymentMock
            ->expects($this->any())
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
                ->method('setAdditionalInformation')
                ->with('skip_push', $skipPush);
        }

        $this->skipPushHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }

    public function skipPushDataProvider()
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
