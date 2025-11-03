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
        $this->orderPaymentMock->method('setAdditionalInformation')
            ->with(CreditManagementOrderHandler::INVOICE_KEY, $invoiceKey);

        $this->transactionResponse->method('data')
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
        $method->setAccessible(true);
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
        $method->setAccessible(true);
        $result = $method->invoke($this->creditManagementOrderHandler, $service);

        $this->assertEquals($service['Parameters'][0]['Value'], $result);
    }
}
