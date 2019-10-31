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

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http as RequestHttp;
use TIG\Buckaroo\Controller\Adminhtml\Giftcard\Save;
use TIG\Buckaroo\Model\GiftcardFactory;
use TIG\Buckaroo\Test\BaseTest;

class SaveTest extends BaseTest
{
    protected $instanceClass = Save::class;

    public function testExecute()
    {
        $requestMock = $this->getFakeMock(RequestHttp::class)->getMock();

        $contextMock = $this->getFakeMock(Context::class)->setMethods(['getRequest'])->getMock();
        $contextMock->expects($this->once())->method('getRequest')->willReturn($requestMock);

        $giftcardFactoryMock = $this->getFakeMock(GiftcardFactory::class, true);

        $instance = $this->getInstance(['context' => $contextMock, 'giftcardFactory' => $giftcardFactoryMock]);
        $instance->execute();
    }
}
