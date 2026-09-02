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

use Buckaroo\Magento2\Gateway\Response\TransactionIdHandler;

class TransactionIdHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var TransactionIdHandler
     */
    private $transactionIdHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionIdHandler = new TransactionIdHandler();
    }

    public function testHandle(): void
    {
        $transactionKey = 'test_transaction_key';

        $this->transactionResponse->expects($this->once())
            ->method('getTransactionKey')
            ->willReturn($transactionKey);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setTransactionId')
            ->with($transactionKey);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(TransactionIdHandler::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setIsTransactionClosed')
            ->with(true);

        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setShouldCloseParentTransaction')
            ->with(true);

        $this->transactionIdHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }
}
