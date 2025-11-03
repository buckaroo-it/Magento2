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

        $this->transactionResponse->method('getTransactionKey')
            ->willReturn($transactionKey);

        $this->orderPaymentMock
            ->method('setTransactionId')
            ->with($transactionKey);

        $this->orderPaymentMock
            ->method('setAdditionalInformation')
            ->with(TransactionIdHandler::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey);

        $this->orderPaymentMock
            ->method('setIsTransactionClosed')
            ->with(true);

        $this->orderPaymentMock
            ->method('setShouldCloseParentTransaction')
            ->with(true);

        $this->transactionIdHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }
}
