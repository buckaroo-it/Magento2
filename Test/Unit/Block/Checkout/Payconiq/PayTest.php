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
namespace TIG\Buckaroo\Test\Unit\Block\Checkout\Payconiq;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template\Context;
use TIG\Buckaroo\Block\Checkout\Payconiq\Pay;
use TIG\Buckaroo\Test\BaseTest;

class PayTest extends BaseTest
{
    protected $instanceClass = Pay::class;

    public function testGetResponse()
    {
        $responseArray = ['Key' => 'abc123', 'invoice_number' => '456'];

        $rqstMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $rqstMock->expects($this->once())->method('getParams')->willReturn($responseArray);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($rqstMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $instance->getResponse();

        $this->assertEquals($responseArray, $result);
    }

    public function getTransactionKeyProvider()
    {
        return [
            'empty value' => [
                ['Key' => ''],
                ''
            ],
            'null value' => [
                ['Key' => null],
                null
            ],
            'string value' => [
                ['Key' => 'abc123def'],
                'abc123def'
            ],
            'int value' => [
                ['Key' => 456987],
                456987
            ],
        ];
    }

    /**
     * @param $response
     * @param $expected
     *
     * @dataProvider getTransactionKeyProvider
     */
    public function testGetTransactionKey($response, $expected)
    {
        $rqstMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParams'])->getMockForAbstractClass();
        $rqstMock->expects($this->once())->method('getParams')->willReturn($response);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($rqstMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $instance->getTransactionKey();

        $this->assertEquals($expected, $result);
    }
}
