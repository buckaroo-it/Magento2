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
    private PaymentDetailsHandler $paymentDetailsHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = $this->createMock(Data::class);
        $this->registry = $this->createMock(Registry::class);
        $this->paymentDetailsHandler = new PaymentDetailsHandler($this->helper, $this->registry);
    }

    public function testHandle(): void
    {
        $arrayResponse = ['some_key' => 'some_value'];
        $this->transactionResponse->expects($this->once())
            ->method('toArray')
            ->willReturn($arrayResponse);

        $this->helper->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->with($arrayResponse)
            ->willReturn($arrayResponse);

        $this->orderPaymentMock->expects($this->once())
            ->method('setTransactionAdditionalInfo')
            ->with(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                json_encode($arrayResponse)
            );

        $this->paymentDetailsHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }

    public function testGetTransactionAdditionalInfo(): void
    {
        $array = ['some_key' => 'some_value'];
        $this->helper->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->with($array)
            ->willReturn($array);

        $result = $this->paymentDetailsHandler->getTransactionAdditionalInfo($array);
        $this->assertEquals($array, $result);
    }

    public function testAddToRegistry(): void
    {
        $key = 'buckaroo_response';
        $value = ['some_key' => 'some_value'];

        $this->registry->expects($this->once())
            ->method('registry')
            ->with($key)
            ->willReturn(null);

        $this->registry->expects($this->once())
            ->method('register')
            ->with($key, [$value]);

        $method = new \ReflectionMethod(PaymentDetailsHandler::class, 'addToRegistry');
        $method->setAccessible(true);
        $method->invoke($this->paymentDetailsHandler, $key, $value);
    }
}
