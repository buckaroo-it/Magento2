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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\Giftcard;


use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\Phrase;
use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Buckaroo\Magento2\Block\Adminhtml\Giftcard\Edit;
use Buckaroo\Magento2\Model\Giftcard;

class EditTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Edit::class;

    public static function headerTextProvider()
    {
        return [
            'existing giftcard uses escaped label' => [
                'id' => 45,
                'label' => 'Card <b>Label</b>',
                'expectedText' => "Edit Giftcard 'Card &lt;b&gt;Label&lt;/b&gt;'"
            ],
            'no id falls back to add title' => [
                'id' => null,
                'label' => 'No ID',
                'expectedText' => 'Add Giftcard'
            ],
            'no id and no label falls back to add title' => [
                'id' => null,
                'label' => null,
                'expectedText' => 'Add Giftcard'
            ]
        ];
    }

    #[DataProvider('headerTextProvider')]
    public function testGetHeaderText($id, $label, $expectedText)
    {
        $objectManager = $this->createMock(\Magento\Framework\ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(
            fn (string $type) => $this->getMockBuilder($type)->disableOriginalConstructor()->getMock()
        );
        \Magento\Framework\App\ObjectManager::setInstance($objectManager);
        \Magento\Framework\Phrase::setRenderer(new \Magento\Framework\Phrase\Renderer\Placeholder());

        $giftcardModel = $this->getFakeMock(Giftcard::class)->onlyMethods(['getLabel', 'getId'])->getMock();
        $giftcardModel->method('getId')->willReturn($id);
        $giftcardModel->method('getLabel')->willReturn($label);

        $buckarooGiftcardData = $this->getFakeMock(BuckarooGiftcardDataInterface::class)->getMock();
        $buckarooGiftcardData->method('getGiftcardModel')->willReturn($giftcardModel);

        $buttonList = $this->getFakeMock(\Magento\Backend\Block\Widget\Button\ButtonList::class)->getMock();
        $urlBuilder = $this->getFakeMock(\Magento\Framework\UrlInterface::class)->getMock();
        $urlBuilder->method('getUrl')->willReturn('http://admin.test/url');

        $request = $this->getFakeMock(\Magento\Framework\App\RequestInterface::class)->getMock();

        $context = $this->getFakeMock(\Magento\Backend\Block\Widget\Context::class)->getMock();
        $context->method('getButtonList')->willReturn($buttonList);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $context->method('getRequest')->willReturn($request);
        $context->method('getEscaper')->willReturn(new \Magento\Framework\Escaper());

        $instance = $this->getInstance([
            'context' => $context,
            'buckarooGiftcardData' => $buckarooGiftcardData,
        ]);
        $result = $instance->getHeaderText();

        $this->assertSame($expectedText, $result);
    }
}
