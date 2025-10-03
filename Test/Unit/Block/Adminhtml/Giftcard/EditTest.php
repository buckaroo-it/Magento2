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

namespace Buckaroo\Magento2\Test\Unit\Block\Adminhtml\Giftcard;

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
            [
                'id' => 45,
                'label' => 'Card Label',
                'expectedArgument' => 'Card Label',
                'expectedText' => "Edit Giftcard '%s'"
            ],
            [
                'id' => null,
                'label' => 'No ID',
                'expectedArgument' => 'Will not validate this',
                'expectedText' => 'Add Giftcard'
            ],
            [
                'id' => null,
                'label' => null,
                'expectedArgument' => null,
                'expectedText' => 'Add Giftcard'
            ]
        ];
    }

    /**
     * @param $id
     * @param $label
     * @param $expectedArgument
     * @param $expectedText
     *
     * @dataProvider headerTextProvider
     */
    public function testGetHeaderText($id, $label, $expectedArgument, $expectedText)
    {
        $giftcardModel = $this->getFakeMock(Giftcard::class)
            ->onlyMethods(['getLabel', 'getId'])
            ->getMock();
        $giftcardModel->method('getId')->willReturn($id);
        $giftcardModel->method('getLabel')->willReturn($label);

        $buckarooGiftcardData = $this->getFakeMock(BuckarooGiftcardDataInterface::class)->getMock();
        $buckarooGiftcardData->method('getGiftcardModel')->willReturn($giftcardModel);

        $contextMock = $this->getFakeMock(\Magento\Backend\Block\Widget\Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $instance = $this->getInstance([
            'context' => $contextMock,
            'buckarooGiftcardData' => $buckarooGiftcardData
        ]);
        $result = $instance->getHeaderText();
        $resultArgs = $result->getArguments();

        $this->assertInstanceOf(Phrase::class, $result);
        $this->assertEquals($expectedText, $result->getText());
        $this->assertIsArray($resultArgs);

        if (isset($resultArgs[0])) {
            $this->assertEquals($expectedArgument, $resultArgs[0]);
        }
    }
}
