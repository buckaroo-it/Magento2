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
