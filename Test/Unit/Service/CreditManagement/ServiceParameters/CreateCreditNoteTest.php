<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace TIG\Buckaroo\Test\Unit\Service\CreditManagement\ServiceParameters;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use TIG\Buckaroo\Service\CreditManagement\ServiceParameters\CreateCreditNote;
use TIG\Buckaroo\Test\BaseTest;

class CreateCreditNoteTest extends BaseTest
{
    protected $instanceClass = CreateCreditNote::class;

    public function testGet()
    {
        $orderMock = $this->getFakeMock(Order::class)->getMock();

        $infoInstanceMock = $this->getFakeMock(Payment::class)
            ->setMethods(['getAdditionalInformation', 'getOrder'])
            ->getMock();
        $infoInstanceMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('buckaroo_cm3_invoice_key')
            ->willReturn('abc');
        $infoInstanceMock->method('getOrder')->willReturn($orderMock);

        $instance = $this->getInstance();
        $result = $instance->get($infoInstanceMock);

        $this->assertInternalType('array', $result);
        $this->assertEquals('CreditManagement3', $result['Name']);
        $this->assertEquals('CreateCreditNote', $result['Action']);
        $this->assertEquals(1, $result['Version']);
        $this->assertCount(4, $result['RequestParameter']);

        $possibleParameters = ['InvoiceAmount', 'InvoiceAmountVat', 'InvoiceDate', 'OriginalInvoiceNumber'];

        foreach ($result['RequestParameter'] as $array) {
            $this->assertArrayHasKey('_', $array);
            $this->assertArrayHasKey('Name', $array);
            $this->assertContains($array['Name'], $possibleParameters);
        }
    }
}
