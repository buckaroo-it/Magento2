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

namespace Buckaroo\Magento2\Model\Config\Source\Email;

use Magento\Framework\Option\ArrayInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;
use Magento\Email\Model\Template\Config;

class Template implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $templatesFactory;

    /**
     * @var Config
     */
    protected $emailConfig;

    /**
     * @param CollectionFactory $templatesFactory
     * @param Config            $emailConfig
     */
    public function __construct(
        CollectionFactory $templatesFactory,
        Config $emailConfig
    ) {
        $this->templatesFactory = $templatesFactory;
        $this->emailConfig = $emailConfig;
    }

    /**
     * Return available email templates as option array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        // Add all Buckaroo second chance default templates from email_templates.xml
        foreach ($this->emailConfig->getAvailableTemplates() as $template) {
            if (strpos($template['value'], 'buckaroo_second_chance') === 0) {
                $options[] = [
                    'value' => $template['value'],
                    'label' => $template['label'] . ' (Default)'
                ];
            }
        }

        // Get custom templates saved in the database
        $customTemplates = $this->templatesFactory->create()
            ->addFieldToFilter('template_code', ['like' => '%second%chance%'])
            ->load();

        foreach ($customTemplates as $template) {
            $options[] = [
                'value' => $template->getId(),
                'label' => $template->getTemplateCode() . ' (Custom)'
            ];
        }

        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        $options = [];

        foreach ($this->toOptionArray() as $option) {
            if (!isset($option['disabled'])) {
                $options[$option['value']] = $option['label'];
            }
        }

        return $options;
    }
}
