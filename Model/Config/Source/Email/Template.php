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

        // Add default SecondChance templates
        $options[] = [
            'value' => 'buckaroo_second_chance_first',
            'label' => __('SecondChance First Email (Default)')
        ];

        $options[] = [
            'value' => 'buckaroo_second_chance_second',
            'label' => __('SecondChance Second Email (Default)')
        ];

        // Get custom templates from the database
        $templates = $this->templatesFactory->create()
            ->addFieldToFilter('template_code', ['like' => '%second%chance%'])
            ->load();

        foreach ($templates as $template) {
            $options[] = [
                'value' => $template->getId(),
                'label' => $template->getTemplateCode() . ' (Custom)'
            ];
        }

        // Get all available email templates that could be used
        $availableTemplates = $this->templatesFactory->create()->load();

        if ($availableTemplates->getSize() > 0) {
            $options[] = [
                'value' => '',
                'label' => __('-- Other Email Templates --'),
                'disabled' => true
            ];

            foreach ($availableTemplates as $template) {
                // Skip if already added above
                $templateCode = strtolower($template->getTemplateCode());
                if (strpos($templateCode, 'second') === false || strpos($templateCode, 'chance') === false) {
                    $options[] = [
                        'value' => $template->getId(),
                        'label' => $template->getTemplateCode()
                    ];
                }
            }
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
