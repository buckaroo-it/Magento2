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

namespace Buckaroo\Magento2\Block\Adminhtml\Config\Support;

use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Module\ModuleResource;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\HTTP\Client\Curl;

class SupportTab extends Template implements RendererInterface
{
    /**
     * @var string
     */
    protected $_template = 'supportTab.phtml';

    /**
     * @var array
     */
    private $phpVersionSupport = [
        '2.4' => ['7.4' => ['+'], '8.1' => ['+'], '8.2' => ['+']],
    ];

    /**
     * @var SoftwareData
     */
    private $softwareData;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * Override the parent constructor to require our own dependencies.
     *
     * @param Context $context
     * @param SoftwareData $softwareData
     * @param Curl $curl
     * @param array $data
     */
    public function __construct(
        Context $context,
        SoftwareData $softwareData,
        Curl $curl,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->softwareData = $softwareData;
        $this->curl = $curl;
    }

    /**
     * Render form element as HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         * @phpstan-ignore-next-line
         */
        $this->setElement($element);

        return $this->toHtml();
    }

    /**
     * Retrieve the version number from constant
     *
     * @return string
     */
    public function getVersionNumber(): string
    {
        return $this->softwareData->getModuleVersion();
    }

    /**
     * PHP Version Check
     *
     * @return bool|int
     */
    public function phpVersionCheck()
    {
        $magentoVersion = $this->getMagentoVersionArray();
        $phpVersion = $this->getPhpVersionArray();

        if (!is_array($magentoVersion) || !is_array($phpVersion)) {
            return -1;
        }

        $magentoMajorMinor = $magentoVersion[0] . '.' . $magentoVersion[1];
        $phpMajorMinor = $phpVersion[0] . '.' . $phpVersion[1];
        $phpPatch = (int)$phpVersion[2];

        if (!isset($this->phpVersionSupport[$magentoMajorMinor]) ||
            !isset($this->phpVersionSupport[$magentoMajorMinor][$phpMajorMinor])
        ) {
            return 0;
        }

        $currentVersion = $this->phpVersionSupport[$magentoMajorMinor][$phpMajorMinor];
        if (!empty($currentVersion)) {
            if (in_array($phpPatch, $currentVersion)) {
                return true;
            } elseif (in_array('+', $currentVersion) && $phpPatch >= max($currentVersion)) {
                return true;
            } else {
                return false;
            }
        }

        return -1;
    }

    /**
     * Get Magento Versions
     *
     * @return array|bool
     */
    private function getMagentoVersionArray()
    {
        $version = false;
        $currentVersion = $this->softwareData->getProductMetaData()->getVersion();

        if (!empty($currentVersion)) {
            $version = explode('.', $currentVersion);
        }

        return $version;
    }

    /**
     * Get PHP Versions
     *
     * @return false|string[]
     */
    private function getPhpVersionArray()
    {
        $version = false;
        if (defined('PHP_VERSION')) {
            $version = explode('.', PHP_VERSION);
        } elseif (function_exists('phpversion')) {
            $version = explode('.', phpversion());
        }

        return $version;
    }

    /**
     * Get latest tag from Github repository
     *
     * @return string
     */
    public function getLatestPluginVersion(): string
    {
        $url = "https://api.github.com/repos/buckaroo-it/Magento2/tags";
        $this->curl->addHeader('User-Agent', 'Magento 2 Buckaroo Plugin');
        $this->curl->get($url);
        $result = json_decode($this->curl->getBody(), true);

        if (is_array($result) && isset($result[0]['name'])) {
            return $result[0]['name'];
        }
        return "v" . $this->getVersionNumber();
    }

    /**
     * Get all php compatible versions
     *
     * @return string
     */
    public function getPhpVersions(): string
    {
        $magentoVersion = $this->getMagentoVersionArray();
        if (!is_array($magentoVersion)) {
            return __('Cannot determine compatible PHP versions');
        }

        $magentoMajorMinor = $magentoVersion[0] . '.' . $magentoVersion[1];
        $versions = array_keys($this->phpVersionSupport[$magentoMajorMinor]);
        return implode(', ', $versions);
    }
}
