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

use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Save;
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
class SaveTest extends BaseTest
{
    private const FORM_DATA = ['label' => 'Gift label', 'servicecode' => 'giftcode'];

    protected $instanceClass = Save::class;

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
        $this->sessionStorage = new Storage();
        $this->giftcard = $this->getFakeMock(Giftcard::class)->onlyMethods(['getId', 'setData'])->getMock();
        $this->giftcardFactory = $this->createMock(GiftcardFactory::class);
        $this->giftcardResource = $this->createMock(GiftcardResource::class);
    }

    public function testExecuteSavesAnExistingGiftcardAndReturnsToTheGrid()
    {
        $this->postForm('5', self::FORM_DATA);
        $this->giftcard->method('getId')->willReturn('5');

        $this->giftcardResource->expects($this->once())->method('load')->with($this->giftcard, '5');
        $this->giftcard->expects($this->once())->method('setData')->with(self::FORM_DATA);
        $this->giftcardResource->expects($this->once())->method('save')->with($this->giftcard);
        $this->expectSuccessMessage();
        $this->messageManager->expects($this->never())->method('addError');
        $this->expectRedirect('*/*/', [], 'https://admin.example/buckaroo/giftcard/');

        $this->assertSame($this->response, $this->buildController()->execute());
        $this->assertNull($this->sessionStorage->getData('form_data'));
    }

    public function testExecuteSaveAndContinueReturnsToTheEditForm()
    {
        $this->postForm(null, self::FORM_DATA, '1');
        $this->giftcard->method('getId')->willReturn('12');

        $this->giftcardResource->expects($this->never())->method('load');
        $this->giftcard->expects($this->once())->method('setData')->with(self::FORM_DATA);
        $this->giftcardResource->expects($this->once())->method('save')->with($this->giftcard);
        $this->expectSuccessMessage();
        $this->expectRedirect(
            '*/*/edit',
            ['entity_id' => '12', '_current' => true],
            'https://admin.example/buckaroo/giftcard/edit/entity_id/12/'
        );

        $this->assertSame($this->response, $this->buildController()->execute());
    }

    public function testExecuteShowsTheErrorAndKeepsTheFormWhenSavingFails()
    {
        $this->postForm('5', self::FORM_DATA);
        $this->giftcard->method('getId')->willReturn('5');

        $this->giftcardResource->expects($this->once())->method('save')
            ->willThrowException(new \Exception('Duplicate service code'));
        $this->messageManager->expects($this->never())->method('addSuccess');
        $this->messageManager->expects($this->once())->method('addError')->with('Duplicate service code');
        $this->expectRedirect('*/*/edit', ['entity_id' => '5'], 'https://admin.example/buckaroo/giftcard/edit/entity_id/5/');

        $this->assertSame($this->response, $this->buildController()->execute());
        $this->assertSame(self::FORM_DATA, $this->sessionStorage->getData('form_data'));
    }

    public function testExecuteClearsTheLogoWhenItsDeleteBoxIsTicked()
    {
        $this->postForm('5', ['label' => 'Gift label', 'logo' => ['delete' => '1', 'value' => 'buckaroo/a/b/old.png']]);
        $this->giftcard->method('getId')->willReturn('5');

        $this->giftcard->expects($this->once())->method('setData')->with(['label' => 'Gift label', 'logo' => '']);
        $this->giftcardResource->expects($this->once())->method('save')->with($this->giftcard);
        $this->expectRedirect('*/*/', [], 'https://admin.example/buckaroo/giftcard/');

        $this->buildController()->execute();
    }

    public function testExecuteWithoutPostDataDoesNothing()
    {
        $this->request->method('getPost')->willReturn([]);

        $this->giftcardFactory->expects($this->never())->method('create');
        $this->giftcardResource->expects($this->never())->method('save');
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertNull($this->buildController()->execute());
    }

    /**
     * @param string|null $giftcardId
     * @param array       $formData
     * @param string|null $back
     */
    private function postForm(?string $giftcardId, array $formData, ?string $back = null): void
    {
        $this->request->method('getPost')->willReturn(['giftcard' => $formData]);
        $this->request->method('getParam')->willReturnMap([
            ['entity_id', null, $giftcardId],
            ['giftcard', null, $formData],
            ['back', null, $back],
        ]);
        $this->request->method('getFiles')->with('logo')->willReturn(null);
        $this->giftcardFactory->expects($this->once())->method('create')->willReturn($this->giftcard);
    }

    private function expectSuccessMessage(): void
    {
        $this->messageManager->expects($this->once())->method('addSuccess')
            ->with($this->callback(static fn($text) => (string)$text === 'The giftcard has been saved.'));
    }

    /**
     * @param string $route
     * @param array  $params
     * @param string $url
     */
    private function expectRedirect(string $route, array $params, string $url): void
    {
        $this->helper->expects($this->once())->method('getUrl')->with($route, $params)->willReturn($url);
        $this->response->expects($this->once())->method('setRedirect')->with($url);
    }

    private function buildController(): Save
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

        return new Save(
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
