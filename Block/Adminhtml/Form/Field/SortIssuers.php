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

namespace Buckaroo\Magento2\Block\Adminhtml\Form\Field;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SortIssuers extends Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::form/field/sort_issuers.phtml';

    /**
     * @var array
     */
    protected $configuration = [];

    /**
     * @var array
     */
    protected $issuers = [];

    /**
     * @var ConfigProviderFactory
     */
    protected $configProviderFactory;

    /**
     * @var ?ConfigProviderInterface
     */
    protected $configProvider = null;

    /**
     * @param Context               $context
     * @param ConfigProviderFactory $configProviderFactory
     * @param array                 $data
     */
    public function __construct(
        Context $context,
        ConfigProviderFactory $configProviderFactory,
        array $data = []
    ) {
        $this->configProviderFactory = $configProviderFactory;
        parent::__construct($context, $data);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     *
     * @throws Exception
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->configuration = [];
        $this->issuers = [];
        $this->configuration['name'] = $element->getName();
        $this->configuration['ccInherit'] = str_replace('value', 'inherit', $element->getName());
        $this->configuration['selector'] = $this->getSelector($element->getName());
        $fieldConfig = $element->getData('field_config');
        $configPath = $fieldConfig['config_path'] ?? null;

        if ($configPath) {
            $pathParts = explode('/', $configPath);

            // The group ID should be the second element in the array
            $groupId = $pathParts[1] ?? null;
            $providerType = str_replace('buckaroo_magento2_', '', $groupId);
            $this->configuration['providerType'] = $providerType;

            $this->configProvider = $this->configProviderFactory->get($providerType);
        }

        return $this->_toHtml();
    }

    /**
     * @return array
     */
    public function getIssuers(): array
    {
        if (!empty($this->issuers)) {
            return $this->issuers;
        }

        if ($this->configProvider && method_exists($this->configProvider, 'getAllIssuers')) {
            $this->issuers = $this->configProvider->getAllIssuers();
        }

        return $this->issuers;
    }

    /**
     * @return array
     */
    public function getSortedIssuers(): array
    {
        if (!empty($this->issuers)) {
            return $this->issuers;
        }

        if ($this->configProvider && method_exists($this->configProvider, 'formatIssuers')) {
            return $this->configProvider->formatIssuers();
        }

        return [];
    }

    public function getSortedIssuerCodes()
    {
        $sortedIssuerCodes = '';

        if ($this->configProvider && method_exists($this->configProvider, 'getSortedIssuers')) {
            $sortedIssuerCodes = $this->configProvider->getSortedIssuers();
        }

        if ($sortedIssuerCodes === '') {
            $sortedIssuerCodes = implode(',', array_column($this->getIssuers(), 'code'));
        }

        return $sortedIssuerCodes;
    }

    private function getSelector($elementName)
    {
        $selector = str_replace('sorted_issuers', 'allowed_issuers', $elementName);
        $selector = str_replace('sorted_giftcards', 'allowed_giftcards', $selector);

        return $selector;
    }

    public function getConfiguration($elementName = '')
    {
        return $this->configuration[$elementName] ?? $this->configuration;
    }
}
