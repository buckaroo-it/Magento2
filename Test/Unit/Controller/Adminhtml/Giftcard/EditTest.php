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

use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Edit;
use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Buckaroo\Magento2\Model\Giftcard;
use Buckaroo\Magento2\Model\GiftcardFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard as GiftcardResource;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Backend\Model\Session;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\App\Response\Http as Response;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\Storage;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EditTest extends BaseTest
{
    protected $instanceClass = Edit::class;

    /** @var Request|\PHPUnit\Framework\MockObject\MockObject */
    private $request;

    /** @var Response|\PHPUnit\Framework\MockObject\MockObject */
    private $response;

    /** @var ManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageManager;

    /** @var BackendHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $helper;

    /** @var Storage */
    private $sessionStorage;

    /** @var Giftcard|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcard;

    /** @var GiftcardResource|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcardResource;

    /** @var BuckarooGiftcardDataInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $giftcardData;

    /** @var PageFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $pageFactory;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->helper = $this->createMock(BackendHelper::class);
        $this->sessionStorage = new Storage();
        $this->giftcard = $this->getFakeMock(Giftcard::class)->onlyMethods(['getId', 'setData'])->getMock();
        $this->giftcardResource = $this->createMock(GiftcardResource::class);
        $this->giftcardData = $this->createMock(BuckarooGiftcardDataInterface::class);
        $this->pageFactory = $this->createMock(PageFactory::class);
    }

    public function testExecuteShowsTheFormForAnExistingGiftcard()
    {
        $this->request->method('getParam')->willReturnMap([['entity_id', null, '7']]);
        $this->giftcard->method('getId')->willReturn('7');
        $this->giftcardResource->expects($this->once())->method('load')->with($this->giftcard, '7');
        $this->giftcard->expects($this->never())->method('setData');
        $this->giftcardData->expects($this->once())->method('setGiftcardModel')->with($this->giftcard);
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $page = $this->expectGiftcardPage();

        $this->assertSame($page, $this->buildController()->execute());
    }

    public function testExecuteRestoresFormDataKeptInTheSession()
    {
        $formData = ['label' => 'Restored label', 'servicecode' => 'restoredcard'];
        $this->sessionStorage->setData('form_data', $formData);

        $this->request->method('getParam')->willReturnMap([['entity_id', null, null]]);
        $this->giftcardResource->expects($this->never())->method('load');
        $this->giftcard->expects($this->once())->method('setData')->with($formData);
        $this->giftcardData->expects($this->once())->method('setGiftcardModel')->with($this->giftcard);

        $page = $this->expectGiftcardPage();

        $this->assertSame($page, $this->buildController()->execute());
    }

    public function testExecuteRedirectsToTheGridWhenTheGiftcardNoLongerExists()
    {
        $this->request->method('getParam')->willReturnMap([['entity_id', null, '99']]);
        $this->giftcard->method('getId')->willReturn(null);
        $this->giftcardResource->expects($this->once())->method('load')->with($this->giftcard, '99');

        $this->messageManager->expects($this->once())->method('addErrorMessage')
            ->with($this->callback(static fn($text) => (string)$text === 'This giftcard no longer exists.'));
        $this->helper->expects($this->once())->method('getUrl')->with('*/*/', [])
            ->willReturn('https://admin.example/buckaroo/giftcard/');
        $this->response->expects($this->once())->method('setRedirect')
            ->with('https://admin.example/buckaroo/giftcard/');

        $this->giftcardData->expects($this->never())->method('setGiftcardModel');
        $this->pageFactory->expects($this->never())->method('create');

        $this->assertSame($this->response, $this->buildController()->execute());
    }

    /**
     * @return Page|\PHPUnit\Framework\MockObject\MockObject
     */
    private function expectGiftcardPage()
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())->method('prepend')
            ->with($this->callback(static fn($text) => (string)$text === 'Buckaroo Giftcards'));

        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createMock(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->expects($this->once())->method('setActiveMenu')
            ->with('Buckaroo_Magento2::buckaroo_giftcards')
            ->willReturnSelf();

        $this->pageFactory->expects($this->once())->method('create')->willReturn($page);

        return $page;
    }

    private function buildController(): Edit
    {
        // A real backend session over real storage, so the controller's magic session calls work.
        $session = (new \ReflectionClass(Session::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Session::class, 'storage'))->setValue($session, $this->sessionStorage);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getResponse')->willReturn($this->response);
        $context->method('getMessageManager')->willReturn($this->messageManager);
        $context->method('getHelper')->willReturn($this->helper);
        $context->method('getSession')->willReturn($session);
        $context->method('getActionFlag')->willReturn($this->createMock(ActionFlag::class));

        $giftcardFactory = $this->createMock(GiftcardFactory::class);
        $giftcardFactory->expects($this->once())->method('create')->willReturn($this->giftcard);

        return new Edit(
            $context,
            $this->pageFactory,
            $giftcardFactory,
            $this->giftcardData,
            $this->giftcardResource
        );
    }
}
