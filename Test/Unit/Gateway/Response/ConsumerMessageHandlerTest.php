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

use Buckaroo\Magento2\Gateway\Response\CancelHandler;
use Buckaroo\Magento2\Gateway\Response\ConsumerMessageHandler;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use PHPUnit\Framework\MockObject\MockObject;

class ConsumerMessageHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var MessageManager|MockObject
     */
    protected $messageManager;

    /**
     * @var ConsumerMessageHandler
     */
    private ConsumerMessageHandler $consumerMessageHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageManager = $this->getMockBuilder(MessageManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->consumerMessageHandler = new ConsumerMessageHandler($this->messageManager);
    }

    /**
     * @dataProvider messageDataProvider
     *
     * @param array $consumerMessage
     * @param int $expectedMessageCalls
     * @return void
     */
    public function testHandle(array $consumerMessage, int $expectedMessageCalls): void
    {
        $this->transactionResponse
            ->expects($this->once())
            ->method('get')
            ->with('ConsumerMessage')
            ->willReturn($consumerMessage);

        $this->messageManager->expects($this->exactly($expectedMessageCalls))
            ->method('addSuccessMessage');

        $this->consumerMessageHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function messageDataProvider(): array
    {
        return [
            'empty data' => [
                [],
                0
            ],
            'MustRead not 1' => [
                ['MustRead' => 0, 'Title' => 'Sample Title 0', 'PlainText' => 'Sample Text 1'],
                0
            ],
            'Title and PlainText set' => [
                ['MustRead' => 1, 'Title' => 'Sample Title', 'PlainText' => 'Sample Text'],
                2
            ],
            'only Title set' => [
                ['MustRead' => 1, 'Title' => 'Sample Title'],
                1
            ],
            'only PlainText set' => [
                ['MustRead' => 1, 'PlainText' => 'Sample Text'],
                1
            ],
            'Title and PlainText not set' => [
                ['MustRead' => 1],
                0
            ],
        ];
    }
}
