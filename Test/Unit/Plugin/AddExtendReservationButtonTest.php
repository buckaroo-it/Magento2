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

namespace Buckaroo\Magento2\Test\Unit\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ExtendReservation;
use Buckaroo\Magento2\Plugin\AddExtendReservationButton;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddExtendReservationButtonTest extends TestCase
{
    /**
     * @var AddExtendReservationButton
     */
    private AddExtendReservationButton $plugin;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var ExtendReservation|MockObject
     */
    private $extendReservationMock;

    /**
     * @var UrlInterface|MockObject
     */
    private $urlBuilderMock;

    /**
     * @var Toolbar|MockObject
     */
    private $toolbarMock;

    /**
     * @var ButtonList|MockObject
     */
    private $buttonListMock;

    protected function setUp(): void
    {
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->extendReservationMock = $this->createMock(ExtendReservation::class);
        $this->urlBuilderMock = $this->createMock(UrlInterface::class);
        $this->toolbarMock = $this->createMock(Toolbar::class);
        $this->buttonListMock = $this->createMock(ButtonList::class);

        $this->plugin = new AddExtendReservationButton(
            $this->orderRepositoryMock,
            $this->extendReservationMock,
            $this->urlBuilderMock,
            $this->createMock(BuckarooLoggerInterface::class)
        );
    }

    public function testAddsButtonWhenReservationCanBeExtended(): void
    {
        // Arrange
        $orderMock = $this->createMock(Order::class);
        $contextMock = $this->createContextMock('123', 'sales_order_view');

        $this->orderRepositoryMock->method('get')->with(123)->willReturn($orderMock);
        $this->extendReservationMock->method('canExtend')->with($orderMock)->willReturn(true);
        $this->urlBuilderMock->method('getUrl')
            ->with('buckaroo/klarna/extendreservation', ['order_id' => '123'])
            ->willReturn('https://admin.example.com/buckaroo/klarna/extendreservation/order_id/123');

        // Assert
        $this->buttonListMock->expects($this->once())
            ->method('add')
            ->with('extendKlarnaReservationButton', $this->isType('array'), -1);

        // Act
        $this->plugin->beforePushButtons($this->toolbarMock, $contextMock, $this->buttonListMock);
    }

    public function testDoesNotAddButtonWithoutOrderIdParam(): void
    {
        // Arrange
        $contextMock = $this->createContextMock(null, 'sales_order_view');

        // Assert
        $this->orderRepositoryMock->expects($this->never())->method('get');
        $this->buttonListMock->expects($this->never())->method('add');

        // Act
        $this->plugin->beforePushButtons($this->toolbarMock, $contextMock, $this->buttonListMock);
    }

    public function testDoesNotAddButtonOnOtherAdminPages(): void
    {
        // Arrange
        $contextMock = $this->createContextMock('123', 'sales_order_invoice_view');

        // Assert
        $this->orderRepositoryMock->expects($this->never())->method('get');
        $this->buttonListMock->expects($this->never())->method('add');

        // Act
        $this->plugin->beforePushButtons($this->toolbarMock, $contextMock, $this->buttonListMock);
    }

    public function testDoesNotAddButtonWhenReservationCannotBeExtended(): void
    {
        // Arrange
        $orderMock = $this->createMock(Order::class);
        $contextMock = $this->createContextMock('123', 'sales_order_view');

        $this->orderRepositoryMock->method('get')->with(123)->willReturn($orderMock);
        $this->extendReservationMock->method('canExtend')->with($orderMock)->willReturn(false);

        // Assert
        $this->buttonListMock->expects($this->never())->method('add');

        // Act
        $this->plugin->beforePushButtons($this->toolbarMock, $contextMock, $this->buttonListMock);
    }

    public function testDoesNotAddButtonWhenOrderCannotBeLoaded(): void
    {
        // Arrange
        $contextMock = $this->createContextMock('999', 'sales_order_view');

        $this->orderRepositoryMock->method('get')
            ->with(999)
            ->willThrowException(new \Exception('Order not found'));

        // Assert
        $this->buttonListMock->expects($this->never())->method('add');

        // Act
        $this->plugin->beforePushButtons($this->toolbarMock, $contextMock, $this->buttonListMock);
    }

    /**
     * Create a block context mock exposing the given request parameters.
     *
     * @param string|null $orderId
     * @param string      $fullActionName
     *
     * @return AbstractBlock|MockObject
     */
    private function createContextMock(?string $orderId, string $fullActionName)
    {
        $requestMock = $this->getMockBuilder(RequestInterface::class)
            ->onlyMethods(['getParam'])
            ->addMethods(['getFullActionName'])
            ->getMockForAbstractClass();
        $requestMock->method('getParam')->with('order_id')->willReturn($orderId);
        $requestMock->method('getFullActionName')->willReturn($fullActionName);

        $contextMock = $this->createMock(AbstractBlock::class);
        $contextMock->method('getRequest')->willReturn($requestMock);

        return $contextMock;
    }
}
