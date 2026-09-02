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

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Index;
use Buckaroo\Magento2\Model\GiftcardFactory;

class IndexTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Index::class;

    public function testExecute()
    {
        // Create title mock with prepend method
        $titleMock = $this->createMock(\Magento\Framework\View\Page\Title::class);
        $titleMock->method('prepend')->willReturnSelf();

        // Create config mock that returns title mock
        $configMock = $this->createMock(\Magento\Framework\View\Page\Config::class);
        $configMock->method('getTitle')->willReturn($titleMock);

        // Create page mock using the correct Backend page class with setActiveMenu method
        $pageMock = $this->createMock(\Magento\Backend\Model\View\Result\Page::class);
        $pageMock->method('getConfig')->willReturn($configMock);
        $pageMock->method('setActiveMenu')->willReturnSelf();

        // Create result factory mock
        $resultFactoryMock = $this->createMock(\Magento\Framework\View\Result\PageFactory::class);
        $resultFactoryMock->method('create')->willReturn($pageMock);

        $instance = $this->getInstance(['resultPageFactory' => $resultFactoryMock]);

        $result = $instance->execute();

        $this->assertInstanceOf(\Magento\Backend\Model\View\Result\Page::class, $result);
    }
}
