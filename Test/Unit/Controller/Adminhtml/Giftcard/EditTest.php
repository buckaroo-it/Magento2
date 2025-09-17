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

namespace Buckaroo\Magento2\Test\Unit\Controller\Adminhtml\Giftcard;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Buckaroo\Magento2\Controller\Adminhtml\Giftcard\Edit;
use Buckaroo\Magento2\Model\Giftcard;
use Buckaroo\Magento2\Model\GiftcardFactory;
use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Magento\Backend\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Buckaroo\Magento2\Test\BaseTest;

class EditTest extends BaseTest
{
    protected $instanceClass = Edit::class;

    public function testExecute()
    {
        // Create giftcard model mock
        $giftcardMock = $this->createMock(Giftcard::class);
        $giftcardMock->method('getId')->willReturn(1);
        $giftcardMock->method('load')->willReturnSelf();
        $giftcardMock->method('setData')->willReturnSelf();

        // Create giftcard factory mock
        $giftcardFactoryMock = $this->createMock(GiftcardFactory::class);
        $giftcardFactoryMock->method('create')->willReturn($giftcardMock);

        // Create buckaroo giftcard data interface mock
        $buckarooGiftcardDataMock = $this->createMock(BuckarooGiftcardDataInterface::class);
        $buckarooGiftcardDataMock->method('setGiftcardModel')->with($giftcardMock)->willReturnSelf();

        // Create session mock - use addMethods for getFormData
        $sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['getFormData'])
            ->getMock();
        $sessionMock->method('getFormData')->willReturn([]);

        // Create message manager mock
        $messageManagerMock = $this->createMock(ManagerInterface::class);

        // Create title mock with prepend method
        $titleMock = $this->createMock(\Magento\Framework\View\Page\Title::class);
        $titleMock->method('prepend')->willReturnSelf();

        // Create config mock that returns title mock
        $configMock = $this->createMock(\Magento\Framework\View\Page\Config::class);
        $configMock->method('getTitle')->willReturn($titleMock);

        // Create page mock using the correct Backend page class
        $pageMock = $this->createMock(\Magento\Backend\Model\View\Result\Page::class);
        $pageMock->method('getConfig')->willReturn($configMock);
        $pageMock->method('setActiveMenu')->willReturnSelf();

        // Create result factory mock
        $resultFactoryMock = $this->createMock(\Magento\Framework\View\Result\PageFactory::class);
        $resultFactoryMock->method('create')->willReturn($pageMock);

        $instance = $this->getInstance([
            'resultPageFactory' => $resultFactoryMock,
            'giftcardFactory' => $giftcardFactoryMock,
            'buckarooGiftcardData' => $buckarooGiftcardDataMock,
            '_session' => $sessionMock,
            'messageManager' => $messageManagerMock
        ]);

        $result = $instance->execute();

        $this->assertInstanceOf(\Magento\Backend\Model\View\Result\Page::class, $result);
    }
}
