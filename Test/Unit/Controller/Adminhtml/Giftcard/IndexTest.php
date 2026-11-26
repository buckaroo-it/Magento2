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

use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Index;
use Buckaroo\Magento2\Model\GiftcardFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard as GiftcardResource;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;

class IndexTest extends BaseTest
{
    protected $instanceClass = Index::class;

    public function testExecuteShowsTheGiftcardGridPage()
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

        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->expects($this->once())->method('create')->willReturn($page);

        $instance = new Index(
            $this->createMock(Context::class),
            $pageFactory,
            $this->createMock(GiftcardFactory::class),
            $this->createMock(Filesystem::class),
            $this->createMock(UploaderFactory::class),
            $this->createMock(AdapterFactory::class),
            $this->createMock(GiftcardResource::class)
        );

        $this->assertSame($page, $instance->execute());
    }
}
