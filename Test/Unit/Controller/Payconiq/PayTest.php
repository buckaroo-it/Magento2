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

namespace Buckaroo\Magento2\Test\Unit\Controller\Payconiq;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Buckaroo\Magento2\Controller\Payconiq\Pay;

class PayTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Pay::class;

    public function testExecuteCanNotShowPage()
    {
        $requestMock = $this->getFakeMock(RequestInterface::class)
            ->onlyMethods(['getParam'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->with('Key')->willReturn(null);
        // Note: setActionName and setDispatched methods don't exist in RequestInterface
        // These calls should be on the response or handled differently

        // Add response mock for redirect() calls using HTTP response interface
        $responseMock = $this->getMockBuilder(\Magento\Framework\App\Response\Http::class)
            ->onlyMethods(['setRedirect'])
            ->disableOriginalConstructor()
            ->getMock();
        $responseMock->method('setRedirect')->willReturnSelf();

        // Add redirect mock for _redirect() method
        $redirectMock = $this->createMock(\Magento\Framework\App\Response\RedirectInterface::class);
        $redirectMock->method('redirect')->willReturn(null);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest', 'getResponse', 'getRedirect'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);
        $contextMock->method('getResponse')->willReturn($responseMock);
        $contextMock->method('getRedirect')->willReturn($redirectMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock
        ]);

        $result = $instance->execute();
        $this->assertNotNull($result); // _redirect returns response object
    }

    public function testExecuteCanShowPage()
    {
        $pageMock = $this->getFakeMock(Page::class, true);

        $pageFactoryMock = $this->getFakeMock(PageFactory::class)->onlyMethods(['create'])->getMock();
        $pageFactoryMock->method('create')->willReturn($pageMock);

        $requestMock = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('Key')->willReturn('abc123');

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,'context' => $contextMock, 'resultPageFactory' => $pageFactoryMock]);

        $result = $instance->execute();

        $this->assertInstanceOf(Page::class, $result);
    }

    public static function canShowPageProvider()
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
        $requestMock = $this->getFakeMock(RequestInterface::class)->onlyMethods(['getParam'])->getMockForAbstractClass();
        $requestMock->method('getParam')->with('Key')->willReturn($key);

        $contextMock = $this->getFakeMock(Context::class)->onlyMethods(['getRequest'])->getMock();
        $contextMock->method('getRequest')->willReturn($requestMock);

        // Add redirectFactory mock for Action controllers
        $redirectMock = $this->createMock(\Magento\Framework\Controller\Result\Redirect::class);
        $redirectFactoryMock = $this->createMock(\Magento\Framework\Controller\Result\RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        $instance = $this->getInstance([
            'redirectFactory' => $redirectFactoryMock,
            'context' => $contextMock
        ]);
        $result = $this->invoke('canShowPage', $instance);

        $this->assertEquals($expected, $result);
    }
}
