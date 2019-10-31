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
namespace TIG\Buckaroo\Test\Unit\Block\Adminhtml\Giftcard;

use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use TIG\Buckaroo\Block\Adminhtml\Giftcard\Edit;
use TIG\Buckaroo\Model\Giftcard;

class EditTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Edit::class;

    public function headerTextProvider()
    {
        return array(
            array(
                'id' => 45,
                'label' => 'Card Label',
                'expectedArgument' => 'Card Label',
                'expectedText' => "Edit Giftcard '%s'"
            ),
            array(
                'id' => null,
                'label' => 'No ID',
                'expectedArgument' => 'Will not validate this',
                'expectedText' => 'Add Giftcard'
            ),
            array(
                'id' => null,
                'label' => null,
                'expectedArgument' => null,
                'expectedText' => 'Add Giftcard'
            )
        );
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
        $giftcardModel = $this->getFakeMock(Giftcard::class)->setMethods(['getId', 'getLabel'])->getMock();
        $giftcardModel->method('getId')->willReturn($id);
        $giftcardModel->method('getLabel')->willReturn($label);

        $registry = $this->getFakeMock(Registry::class)->setMethods(['registry'])->getMock();
        $registry->method('registry')->with('buckaroo_giftcard')->willReturn($giftcardModel);

        $instance = $this->getInstance(['registry' => $registry]);
        $result = $instance->getHeaderText();
        $resultArgs = $result->getArguments();

        $this->assertInstanceOf(Phrase::class, $result);
        $this->assertEquals($expectedText, $result->getText());
        $this->assertInternalType('array', $resultArgs);

        if (isset($resultArgs[0])) {
            $this->assertEquals($expectedArgument, $resultArgs[0]);
        }
    }
}
