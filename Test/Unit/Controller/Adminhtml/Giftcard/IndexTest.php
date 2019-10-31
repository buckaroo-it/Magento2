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
namespace TIG\Buckaroo\Test\Unit\Controller\Adminhtml\Giftcard;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use TIG\Buckaroo\Controller\Adminhtml\Giftcard\Index;
use TIG\Buckaroo\Model\GiftcardFactory;

class IndexTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Index::class;

    public function testExecute()
    {
        $resultPageMock = $this->getFakeMock(Page::class)
            ->setMethods(['setActiveMenu', 'getConfig', 'getTitle', 'prepend'])
            ->getMock();
        $resultPageMock->expects($this->once())->method('setActiveMenu')->willReturnSelf();
        $resultPageMock->expects($this->once())->method('getConfig')->willReturnSelf();
        $resultPageMock->expects($this->once())->method('getTitle')->willReturnSelf();
        $resultPageMock->expects($this->once())->method('prepend')->with('Buckaroo Giftcards');

        $resultPageFactoryMock = $this->getFakeMock(PageFactory::class)->setMethods(['create'])->getMock();
        $resultPageFactoryMock->expects($this->once())->method('create')->willReturn($resultPageMock);

        $giftcardFactoryMock = $this->getFakeMock(GiftcardFactory::class, true);

        $instance = $this->getInstance([
            'resultPageFactory' => $resultPageFactoryMock,
            'giftcardFactory' => $giftcardFactoryMock
        ]);
        $instance->execute();
    }
}
