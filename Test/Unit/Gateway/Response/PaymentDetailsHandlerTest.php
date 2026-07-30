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

use Buckaroo\Magento2\Gateway\Response\PaymentDetailsHandler;
use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentDetailsHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var Data|MockObject
     */
    private $helper;

    /**
     * @var MockObject|Registry
     */
    private $registry;

    /**
     * @var PaymentDetailsHandler
     */
    private $paymentDetailsHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = $this->createMock(Data::class);
        $this->registry = $this->createMock(\Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface::class);
        $this->paymentDetailsHandler = new PaymentDetailsHandler($this->helper, $this->registry);
    }

    public function testHandle(): void
    {
        $arrayResponse = ['some_key' => 'some_value'];
        $rawInfo = ['normalized_key' => 'normalized_value'];

        $this->transactionResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($arrayResponse);

        $this->helper->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->with($arrayResponse)
            ->willReturn($rawInfo);

        $this->orderPaymentMock->expects($this->once())
            ->method('setTransactionAdditionalInfo')
            ->with(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                json_encode($rawInfo)
            );

        // The handler must register the raw transaction response for later use.
        $this->registry->expects($this->once())
            ->method('setResponse')
            ->with($this->transactionResponse);

        $this->paymentDetailsHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }

    public function testGetTransactionAdditionalInfo(): void
    {
        $array = ['some_key' => 'some_value'];
        $normalized = ['normalized_key' => 'normalized_value'];

        $this->helper->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->with($array)
            ->willReturn($normalized);

        $result = $this->paymentDetailsHandler->getTransactionAdditionalInfo($array);

        // Must return exactly what the helper produced, proving delegation (not an echo of the input).
        $this->assertSame($normalized, $result);
    }

    public function testHandleSkipsWhenGroupTransactionRefundComplete(): void
    {
        // When a group transaction refund has already completed, the handler must be a no-op:
        // it must not touch the payment or register a response.
        $this->transactionResponse->expects($this->never())->method('toArray');
        $this->helper->expects($this->never())->method('getTransactionAdditionalInfo');
        $this->orderPaymentMock->expects($this->never())->method('setTransactionAdditionalInfo');
        $this->registry->expects($this->never())->method('setResponse');

        $response = $this->getTransactionResponse();
        $response['group_transaction_refund_complete'] = true;

        $this->paymentDetailsHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $response
        );
    }
}
