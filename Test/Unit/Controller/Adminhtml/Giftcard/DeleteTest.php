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

namespace Buckaroo\Magento2\Test\Unit\Controller\Adminhtml\Giftcard;

use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Delete;
use Buckaroo\Magento2\Model\Giftcard;
use Buckaroo\Magento2\Model\GiftcardFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard as GiftcardResource;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Backend\Model\Session;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Response\Http as Response;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\Storage;
use Magento\Framework\View\Result\PageFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DeleteTest extends BaseTest
{
    protected $instanceClass = Delete::class;

    /** @var Request|\PHPUnit\Framework\MockObject\MockObject */
    private $request;

    /** @var Response|\PHPUnit\Framework\MockObject\MockObject */
    private $response;

    /** @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageManager;

    /** @var BackendHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $helper;

    /** @var Giftcard|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcard;

    /** @var GiftcardFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcardFactory;

    /** @var GiftcardResource|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcardResource;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->helper = $this->createMock(BackendHelper::class);
        $this->giftcard = $this->getFakeMock(Giftcard::class)->onlyMethods(['getId'])->getMock();
        $this->giftcardFactory = $this->createMock(GiftcardFactory::class);
        $this->giftcardResource = $this->createMock(GiftcardResource::class);
    }

    public function testExecuteDeletesTheGiftcardAndReturnsToTheGrid()
    {
        $this->requestGiftcard('7');
        $this->giftcard->method('getId')->willReturn('7');

        $this->giftcardResource->expects($this->once())->method('load')->with($this->giftcard, '7');
        $this->giftcardResource->expects($this->once())->method('delete')->with($this->giftcard);
        $this->messageManager->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(fn ($message) => (string)$message === 'The giftcard has been deleted.'));
        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $this->expectRedirect('*/*/', [], 'https://admin.example/buckaroo/giftcard/');

        $this->assertSame($this->response, $this->buildController()->execute());
    }

    /**
     * After a failed delete the admin returns to that giftcard's edit page, which reads entity_id.
     */
    public function testExecuteReturnsToTheGiftcardsEditPageWhenDeletingFails()
    {
        $this->requestGiftcard('7');
        $this->giftcard->method('getId')->willReturn('7');

        $this->giftcardResource->expects($this->once())->method('delete')
            ->willThrowException(new \Exception('Cannot delete giftcard'));
        $this->messageManager->expects($this->never())->method('addSuccessMessage');
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with('Cannot delete giftcard');
        $this->expectRedirect('*/*/edit', ['entity_id' => '7'], 'https://admin.example/buckaroo/giftcard/edit/entity_id/7/');

        $this->assertSame($this->response, $this->buildController()->execute());
    }

    public function testExecuteReportsAGiftcardThatNoLongerExists()
    {
        $this->requestGiftcard('7');
        $this->giftcard->method('getId')->willReturn(null);

        $this->giftcardResource->expects($this->never())->method('delete');
        $messages = [];
        $this->messageManager->method('addErrorMessage')
            ->willReturnCallback(function ($message) use (&$messages) {
                $messages[] = (string)$message;
                return $this->messageManager;
            });
        $this->expectRedirect('*/*/', [], 'https://admin.example/buckaroo/giftcard/');

        $this->buildController()->execute();

        $this->assertSame(
            ['This giftcard no longer exists.', 'We can\'t find a Giftcard to delete.'],
            $messages
        );
    }

    public function testExecuteWithoutAnIdDeletesNothing()
    {
        $this->request->method('getParam')->with('entity_id')->willReturn(null);
        $this->giftcardFactory->expects($this->never())->method('create');
        $this->giftcardResource->expects($this->never())->method('delete');
        $this->messageManager->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(fn ($message) => (string)$message === 'We can\'t find a Giftcard to delete.'));
        $this->expectRedirect('*/*/', [], 'https://admin.example/buckaroo/giftcard/');

        $this->buildController()->execute();
    }

    private function requestGiftcard(string $giftcardId): void
    {
        $this->request->method('getParam')->with('entity_id')->willReturn($giftcardId);
        $this->giftcardFactory->expects($this->once())->method('create')->willReturn($this->giftcard);
    }

    private function expectRedirect(string $route, array $params, string $url): void
    {
        $this->helper->expects($this->once())->method('getUrl')->with($route, $params)->willReturn($url);
        $this->response->expects($this->once())->method('setRedirect')->with($url);
    }

    private function buildController(): Delete
    {
        // A real backend session over real storage, as the backend redirect writes to it
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Session::class, 'storage'))->setValue($session, new Storage());

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResponse')->willReturn($this->response);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getHelper')->willReturn($this->helper);
        $context->method('getSession')->willReturn($session);
        $context->method('getActionFlag')->willReturn($this->createMock(ActionFlag::class));

        return new Delete(
            $context,
            $this->createMock(PageFactory::class),
            $this->giftcardFactory,
            $this->createMock(Filesystem::class),
            $this->createMock(UploaderFactory::class),
            $this->createMock(AdapterFactory::class),
            $this->giftcardResource
        );
    }
}
