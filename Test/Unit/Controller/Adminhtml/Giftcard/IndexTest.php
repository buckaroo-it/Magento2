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

use TIG\Buckaroo\Controller\Adminhtml\Giftcard\Index;

class IndexTest extends \TIG\Buckaroo\Test\BaseTest
{
    /**
     * @var Index
     */
    protected $controller;

    public function setUp()
    {
        parent::setUp();

        $context = $this->objectManagerHelper->getObject(\Magento\Backend\App\Action\Context::class);
        $registry = \Mockery::mock(\Magento\Framework\Registry::class);

        $resultPageConfig = $this->objectManagerHelper->getObject(\Magento\Framework\View\Page\Config::class);

        $resultPageModel = \Mockery::mock(\Magento\Backend\Model\View\Result\Page::class)->makePartial();
        $resultPageModel->shouldReceive('setActiveMenu')->andReturnSelf();
        $resultPageModel->shouldReceive('getConfig')->andReturn($resultPageConfig);

        $resultPageFactory = \Mockery::mock(\Magento\Framework\View\Result\PageFactory::class);
        $resultPageFactory->shouldReceive('create')->andReturn($resultPageModel);

        $giftcardModel = \Mockery::mock(\TIG\Buckaroo\Model\Giftcard::class)->makePartial();

        $giftcardFactory = \Mockery::mock(\TIG\Buckaroo\Model\GiftcardFactory::class);
        $giftcardFactory->shouldReceive('create')->andReturn($giftcardModel);

        $this->controller = $this->objectManagerHelper->getObject(
            Index::class,
            [
                'context' => $context,
                'coreRegistry' => $registry,
                'resultPageFactory' => $resultPageFactory,
                'giftcardFactory' => $giftcardFactory
            ]
        );
    }

    public function testExecute()
    {
        $this->controller->execute();

        if ($container = \Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
    }
}
