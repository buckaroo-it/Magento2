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

use Buckaroo\Magento2\Gateway\Response\CreditManagementOrderHandler;

class CreditManagementOrderHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var CreditManagementOrderHandler
     */
    private $creditManagementOrderHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creditManagementOrderHandler = new CreditManagementOrderHandler();
    }

    public function testHandle(): void
    {
        $invoiceKey = 'test_invoice_key';
        $this->orderPaymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(CreditManagementOrderHandler::INVOICE_KEY, $invoiceKey);

        $this->transactionResponse->expects($this->once())
            ->method('data')
            ->with('Services')
            ->willReturn([
                [
                    'Name'       => 'CreditManagement3',
                    'Parameters' => [
                        ['Name' => 'InvoiceKey', 'Value' => $invoiceKey],
                    ],
                ],
            ]);

        $this->creditManagementOrderHandler->handle(
            ['payment' => $this->getPaymentDOMock()],
            $this->getTransactionResponse()
        );
    }

    public function testGetCreditManagementService(): void
    {
        $services = [
            ['Name' => 'CreditManagement3', 'Value' => 'test_value'],
            ['Name' => 'AnotherService', 'Value' => 'another_value']
        ];

        $method = new \ReflectionMethod(CreditManagementOrderHandler::class, 'getCreditManagementService');
        $result = $method->invoke($this->creditManagementOrderHandler, $services);

        $this->assertEquals($services[0], $result);
    }

    public function testGetInvoiceKey(): void
    {
        $service = [
            'Parameters' => [
                ['Name' => 'InvoiceKey', 'Value' => 'test_invoice_key'],
                ['Name' => 'AnotherParameter', 'Value' => 'another_value']
            ]
        ];

        $method = new \ReflectionMethod(CreditManagementOrderHandler::class, 'getInvoiceKey');
        $result = $method->invoke($this->creditManagementOrderHandler, $service);

        $this->assertEquals($service['Parameters'][0]['Value'], $result);
    }
}
