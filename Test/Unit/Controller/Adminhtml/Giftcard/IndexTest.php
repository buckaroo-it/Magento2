<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
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
