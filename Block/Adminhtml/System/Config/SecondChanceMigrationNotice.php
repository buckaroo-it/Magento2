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

namespace Buckaroo\Magento2\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Buckaroo\Magento2\Model\SecondChance\ModuleConflictDetector;

class SecondChanceMigrationNotice extends Field
{
    /**
     * @var ModuleConflictDetector
     */
    private $conflictDetector;

    /**
     * @param Context                $context
     * @param ModuleConflictDetector $conflictDetector
     * @param array                  $data
     */
    public function __construct(
        Context $context,
        ModuleConflictDetector $conflictDetector,
        array $data = []
    ) {
        $this->conflictDetector = $conflictDetector;
        parent::__construct($context, $data);
    }

    /**
     * Render element html
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if (!$this->conflictDetector->isOldModuleEnabled()) {
            return '<div class="message message-success">
                <div>✓ SecondChance is now integrated into the main Buckaroo module.</div>
            </div>';
        }

        $instructions = $this->conflictDetector->getMigrationInstructions();

        $html = '<div class="message message-warning">
            <div><strong>⚠️ Migration Required</strong></div>
            <div>The separate SecondChance module is still enabled. Please follow these steps to complete the migration:</div>
            <ol>';

        foreach ($instructions['steps'] as $step) {
            $html .= '<li>' . $this->escapeHtml($step) . '</li>';
        }

        $html .= '</ol>
            <div><strong>Note:</strong> ' . $this->escapeHtml($instructions['data_preservation']) . '</div>
        </div>';

        return $html;
    }

    /**
     * Return element html in one line
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _renderScopeLabel(AbstractElement $element)
    {
        return '';
    }

    /**
     * Return empty label
     *
     * @param AbstractElement $element
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '';
    }
}
