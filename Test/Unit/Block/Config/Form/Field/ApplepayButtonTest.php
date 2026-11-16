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

namespace Buckaroo\Magento2\Test\Unit\Block\Config\Form\Field;

use Buckaroo\Magento2\Block\Config\Form\Field\ApplepayButton;
use Buckaroo\Magento2\Model\Config\Source\ApplepayButtonType;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\AbstractElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplepayButtonTest extends TestCase
{
    private const PREFIX = 'payment_us_buckaroo_magento2_applepay_buckaroo_magento2_default_';

    /**
     * @param mixed $value
     *
     * @return AbstractElement&MockObject
     */
    private function element(string $id, $value = null): MockObject
    {
        $element = $this->getMockBuilder(AbstractElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getDataByKey', 'getForm'])
            ->getMockForAbstractClass();
        $element->method('getId')->willReturn($id);
        $element->method('getDataByKey')->with('value')->willReturn($value);

        return $element;
    }

    /**
     * Render the preview for a form holding the given stored style and type.
     *
     * @param mixed $storedType
     */
    private function renderPreview($storedType): ApplepayButton
    {
        $style = $this->element(self::PREFIX . 'button_style', 'white');
        $type = $this->element(self::PREFIX . 'button_type', $storedType);
        $preview = $this->element(self::PREFIX . 'button_style_preview');

        $form = $this->createMock(Form::class);
        $form->method('getElement')->willReturnMap([
            [self::PREFIX . 'button_style', $style],
            [self::PREFIX . 'button_type', $type],
        ]);
        $preview->method('getForm')->willReturn($form);

        $block = $this->getMockBuilder(ApplepayButton::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['_toHtml'])
            ->getMock();
        $block->method('_toHtml')->willReturn('');

        $buttonTypes = new \ReflectionProperty(ApplepayButton::class, 'buttonTypes');
        $buttonTypes->setAccessible(true);
        $buttonTypes->setValue($block, new ApplepayButtonType());

        $render = new \ReflectionMethod(ApplepayButton::class, '_getElementHtml');
        $render->setAccessible(true);
        $render->invoke($block, $preview);

        return $block;
    }

    public function testFindsTheStyleAndTypeFieldsNextToThePreview(): void
    {
        $block = $this->renderPreview('subscribe');

        $this->assertSame(self::PREFIX . 'button_style', $block->getButtonStyleElement());
        $this->assertSame(self::PREFIX . 'button_type', $block->getButtonTypeElement());
        $this->assertSame('white', $block->getButtonStyle());
        $this->assertSame('subscribe', $block->getButtonType());
    }

    public static function storedTypeProvider(): array
    {
        return [
            'not configured'       => [null],
            'not offered (donate)' => ['donate'],
            'invalid apple value'  => ['checkout'],
        ];
    }

    /**
     * The preview must show what the storefront renders, which falls back to "buy".
     *
     * @param mixed $stored
     */
    #[DataProvider('storedTypeProvider')]
    public function testPreviewFallsBackToBuyLikeTheStorefront($stored): void
    {
        $this->assertSame('buy', $this->renderPreview($stored)->getButtonType());
    }
}
