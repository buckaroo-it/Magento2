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

namespace Buckaroo\Magento2\Test\Unit\Controller\Adminhtml\Klarna;

use Buckaroo\Magento2\Controller\Adminhtml\Klarna\ExtendReservation;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ExtendReservation as ExtendReservationService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExtendReservationTest extends TestCase
{
    /**
     * @var ExtendReservation
     */
    private ExtendReservation $controller;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var ExtendReservationService|MockObject
     */
    private $extendReservationServiceMock;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManagerMock;

    /**
     * @var Redirect|MockObject
     */
    private $redirectResultMock;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->extendReservationServiceMock = $this->createMock(ExtendReservationService::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->redirectResultMock = $this->createMock(Redirect::class);
        $this->redirectResultMock->method('setUrl')->willReturnSelf();

        $resultFactoryMock = $this->createMock(ResultFactory::class);
        $resultFactoryMock->method('create')
            ->with(ResultFactory::TYPE_REDIRECT)
            ->willReturn($this->redirectResultMock);

        $redirectMock = $this->createMock(RedirectInterface::class);
        $redirectMock->method('getRefererUrl')->willReturn('https://admin.example.com/sales/order/view/order_id/123');

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);
        $contextMock->method('getResultFactory')->willReturn($resultFactoryMock);
        $contextMock->method('getRedirect')->willReturn($redirectMock);

        $this->controller = new ExtendReservation(
            $contextMock,
            $this->orderRepositoryMock,
            $this->extendReservationServiceMock,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    public function testShowsErrorWhenOrderIdIsMissing(): void
    {
        // Arrange
        $this->requestMock->method('getParam')->with('order_id')->willReturn(null);

        // Assert
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Order not found.'));
        $this->extendReservationServiceMock->expects($this->never())->method('execute');

        // Act
        $result = $this->controller->execute();
        $this->assertSame($this->redirectResultMock, $result);
    }

    public function testShowsErrorWhenReservationCannotBeExtended(): void
    {
        // Arrange
        $orderMock = $this->createMock(Order::class);
        $this->requestMock->method('getParam')->with('order_id')->willReturn('123');
        $this->orderRepositoryMock->method('get')->with(123)->willReturn($orderMock);
        $this->extendReservationServiceMock->method('canExtend')->with($orderMock)->willReturn(false);

        // Assert
        $this->messageManagerMock->expects($this->once())->method('addErrorMessage');
        $this->extendReservationServiceMock->expects($this->never())->method('execute');

        // Act
        $result = $this->controller->execute();
        $this->assertSame($this->redirectResultMock, $result);
    }

    public function testExtendsReservationAndShowsSuccessMessage(): void
    {
        // Arrange
        $orderMock = $this->createMock(Order::class);
        $this->requestMock->method('getParam')->with('order_id')->willReturn('123');
        $this->orderRepositoryMock->method('get')->with(123)->willReturn($orderMock);
        $this->extendReservationServiceMock->method('canExtend')->with($orderMock)->willReturn(true);

        // Assert
        $this->extendReservationServiceMock->expects($this->once())
            ->method('execute')
            ->with($orderMock)
            ->willReturn(true);
        $this->messageManagerMock->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The Klarna reservation has been extended.'));
        $this->messageManagerMock->expects($this->never())->method('addErrorMessage');

        // Act
        $result = $this->controller->execute();
        $this->assertSame($this->redirectResultMock, $result);
    }

    public function testShowsErrorMessageWhenGatewayRejectsExtension(): void
    {
        // Arrange
        $orderMock = $this->createMock(Order::class);
        $this->requestMock->method('getParam')->with('order_id')->willReturn('123');
        $this->orderRepositoryMock->method('get')->with(123)->willReturn($orderMock);
        $this->extendReservationServiceMock->method('canExtend')->with($orderMock)->willReturn(true);
        $this->extendReservationServiceMock->method('execute')
            ->willThrowException(new LocalizedException(__('Reservation is no longer active')));

        // Assert
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with(__('Unable to extend the Klarna reservation: %1', 'Reservation is no longer active'));
        $this->messageManagerMock->expects($this->never())->method('addSuccessMessage');

        // Act
        $result = $this->controller->execute();
        $this->assertSame($this->redirectResultMock, $result);
    }
}
