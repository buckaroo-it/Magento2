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
namespace TIG\Buckaroo\Test\Unit\Controller\Payconiq;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use TIG\Buckaroo\Controller\Payconiq\Pay;

class PayTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Pay::class;

    public function testExecuteCanNotShowPage()
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)
            ->setMethods(['getParam', 'initForward', 'setActionName', 'setDispatched'])
            ->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('Key')->willReturn(null);
        $requestMock->expects($this->once())->method('initForward');
        $requestMock->expects($this->once())->method('setActionName')->with('defaultNoRoute');
        $requestMock->expects($this->once())->method('setDispatched')->with(false);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);

        $instance->execute();
    }

    public function testExecuteCanShowPage()
    {
        $pageMock = $this->getFakeMock(Page::class, true);

        $pageFactoryMock = $this->getFakeMock(PageFactory::class)->setMethods(['create'])->getMock();
        $pageFactoryMock->expects($this->once())->method('create')->willReturn($pageMock);

        $requestMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('Key')->willReturn('abc123');

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock, 'resultPageFactory' => $pageFactoryMock]);

        $result = $instance->execute();

        $this->assertInstanceOf(Page::class, $result);
    }

    public function canShowPageProvider()
    {
        return [
            'empty value' => [
                '',
                false
            ],
            'null value' => [
                null,
                false
            ],
            'string value' => [
                'abc123def',
                true
            ],
            'int value' => [
                456987,
                true
            ],
        ];
    }

    /**
     * @param $key
     * @param $expected
     *
     * @dataProvider canShowPageProvider
     */
    public function testCanShowPage($key, $expected)
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)->setMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->expects($this->once())->method('getParam')->with('Key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $instance = $this->getInstance(['context' => $contextMock]);
        $result = $this->invoke('canShowPage', $instance);

        $this->assertEquals($expected, $result);
    }
}
