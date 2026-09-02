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
declare(strict_types=1);

namespace Buckaroo\Magento2\Block\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class OptionalBackgroundColorPicker extends Field
{
    /**
     * Marker used to persist an intentionally empty color value.
     */
    private const EMPTY_MARKER = '__EMPTY__';

    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::form/field/optional_background_color_picker.phtml';

    /**
     * @var AbstractElement|null
     */
    private $element;

    /**
     * Render a color picker that can explicitly be set to "no color".
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->element = $element;

        return $this->_toHtml();
    }

    /**
     * Get the HTML id of the underlying form element
     *
     * @return string
     */
    public function getFieldId(): string
    {
        return (string)$this->element->getId();
    }

    /**
     * Get the HTML name of the underlying form element
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return (string)$this->element->getName();
    }

    /**
     * Get the raw stored value of the form element
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return (string)$this->element->getValue();
    }

    /**
     * Get the hex color value to display in the picker, falling back to the default
     *
     * @return string
     */
    public function getPickerValue(): string
    {
        $value = trim($this->getFieldValue());

        if ($this->isEmptyValue()) {
            return '#fefefe';
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#fefefe';
    }

    /**
     * Check whether the stored value represents an intentionally empty color
     *
     * @return bool
     */
    public function isEmptyValue(): bool
    {
        $value = trim($this->getFieldValue());

        return $value === '' || $value === self::EMPTY_MARKER;
    }

    /**
     * Get the marker string used to persist an intentionally empty color value
     *
     * @return string
     */
    public function getEmptyMarker(): string
    {
        return self::EMPTY_MARKER;
    }
}
