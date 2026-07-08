<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Block\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class OptionalColorPicker extends Field
{
    /**
     * Marker used to persist an intentionally empty color value.
     */
    private const EMPTY_MARKER = '__EMPTY__';

    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::form/field/optional_color_picker.phtml';

    /**
     * @var AbstractElement|null
     */
    private $element;

    /**
     * Render a color picker that can explicitly be set to "no value".
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
            return '#d6d6d6';
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#d6d6d6';
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
