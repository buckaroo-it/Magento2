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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\System\Config\Fieldset;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Buckaroo\Magento2\Block\Adminhtml\System\Config\Fieldset\Payment;
use Buckaroo\Magento2\Test\BaseTest;

class PaymentTest extends BaseTest
{
    protected $instanceClass = Payment::class;

    public function setUp(): void
    {
        parent::setUp();
        // Payment's parent (Config Fieldset) pulls SecureHtmlRenderer from the
        // global ObjectManager because Payment::__construct does not forward it.
        $objectManager = $this->createMock(\Magento\Framework\ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(
            fn (string $type) => $this->getMockBuilder($type)->disableOriginalConstructor()->getMock()
        );
        \Magento\Framework\App\ObjectManager::setInstance($objectManager);
    }

    public function testIsCollapseState()
    {

        $instance = $this->getInstance();

        $elementMock = $this->getFakeMock(AbstractElement::class)->getMock();

        $result = $this->invokeArgs('_isCollapseState', [$elementMock], $instance);
        $this->assertFalse($result);
    }

    public function testGetHeaderCommentHtml()
    {

        $instance = $this->getInstance();

        $elementMock = $this->getFakeMock(AbstractElement::class)->getMock();

        $result = $this->invokeArgs('_getHeaderCommentHtml', [$elementMock], $instance);
        $this->assertIsString($result);
        $this->assertEquals(0, strlen($result));
    }
}
