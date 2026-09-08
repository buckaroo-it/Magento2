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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Controller\Redirect\IdinProcess;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Test\BaseTest;
use Buckaroo\Magento2\Test\Unit\Stubs\PushRequestInterfaceStub;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdinProcessTest extends BaseTest
{
    protected $instanceClass = IdinProcess::class;

    /**
     * An unsigned (forged) iDIN return must be rejected before any verification state is written
     * to the checkout session.
     */
    public function testForgedRequestWithInvalidSignatureDoesNotVerifyTheCustomer(): void
    {
        // Arrange: a push that carries data but fails signature validation
        $pushRequestMock = $this->getMockBuilder(PushRequestInterfaceStub::class)->getMock();
        $pushRequestMock->method('getData')->willReturn(['brq_primary_service' => 'IDIN']);
        $pushRequestMock->method('getOriginalRequest')->willReturn([]);
        $pushRequestMock->method('validate')->willReturn(false);

        $requestPushFactoryMock = $this->createMock(RequestPushFactory::class);
        $requestPushFactoryMock->method('create')->willReturn($pushRequestMock);

        // The checkout session must never receive an iDIN verification write on this path.
        $checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $checkoutSessionMock->expects($this->never())->method('__call');

        $instance = $this->getInstance([
            'context' => $this->buildContextMock(),
            'redirectFactory' => $this->buildRedirectFactoryMock(),
            'requestPushFactory' => $requestPushFactoryMock,
            'checkoutSession' => $checkoutSessionMock,
        ]);

        // Act
        $result = $instance->execute();

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @return Context
     */
    private function buildContextMock(): Context
    {
        $response = $this->getFakeMock(ResponseInterface::class)->getMock();

        $request = $this->getFakeMock(RequestInterface::class)->getMock();
        $request->method('getParams')->willReturn([]);

        $redirect = $this->getFakeMock(RedirectInterface::class)->getMock();
        $redirect->method('redirect');

        $messageManagerMock = $this->getFakeMock(ManagerInterface::class)->getMock();
        $messageManagerMock->method('addErrorMessage');

        $contextMock = $this->getFakeMock(Context::class)
            ->onlyMethods(['getRequest', 'getRedirect', 'getResponse', 'getMessageManager'])
            ->getMock();
        $contextMock->method('getRequest')->willReturn($request);
        $contextMock->method('getRedirect')->willReturn($redirect);
        $contextMock->method('getResponse')->willReturn($response);
        $contextMock->method('getMessageManager')->willReturn($messageManagerMock);

        return $contextMock;
    }

    /**
     * @return RedirectFactory
     */
    private function buildRedirectFactoryMock(): RedirectFactory
    {
        $redirectMock = $this->createMock(Redirect::class);
        $redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $redirectFactoryMock->method('create')->willReturn($redirectMock);

        return $redirectFactoryMock;
    }
}
