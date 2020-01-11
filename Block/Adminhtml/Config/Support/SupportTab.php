<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Block\Adminhtml\Config\Support;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;

class SupportTab extends \Magento\Framework\View\Element\Template implements RendererInterface
{
    // @codingStandardsIgnoreStart
    protected $_template = 'supportTab.phtml';
    // @codingStandardsIgnoreEnd

    /** @var array  */
    private $phpVersionSupport = ['2.0' => ['5.5' => ['22','+'],'5.6' => ['+'],'7.0' => ['2', '6', '+']],
                                    '2.1' => ['5.6' => ['5', '+'],'7.0' => ['2', '4', '6', '+']],
                                    '2.2' => ['7.0' => ['2', '4', '6', '+'],'7.1' => ['+']],
                                    '2.3' => ['7.1' => ['3','+'], '7.2' => ['+'], '7.3' => ['+']]
                                ];

    /**
     * @var \Magento\Framework\Setup\ModuleContextInterface
     */
    private $moduleContext;

    /**
     * @var SoftwareData
     */
    private $softwareData;

    /**
     * Override the parent constructor to require our own dependencies.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Module\ModuleResource         $moduleContext
     * @param SoftwareData                                     $softwareData
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Module\ModuleResource $moduleContext,
        SoftwareData $softwareData,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->moduleContext = $moduleContext;
        $this->softwareData = $softwareData;
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->setElement($element);

        return $this->toHtml();
    }

    /**
     * Retrieve the version number from constant
     *
     * @return bool|false|string
     */
    public function getVersionNumber()
    {
        $version = $this->softwareData->getModuleVersion();

        return $version;
    }

    /**
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
        $phpPatch = (int) $phpVersion[2];

        if (!isset($this->phpVersionSupport[$magentoMajorMinor]) ||
            !isset($this->phpVersionSupport[$magentoMajorMinor][$phpMajorMinor])) {
            return 0;
        }

        $currentVersion = $this->phpVersionSupport[$magentoMajorMinor][$phpMajorMinor];
        if (isset($currentVersion)) {

            if (in_array($phpPatch, $currentVersion)) {
                return true;
            }
            elseif(in_array('+', $currentVersion) && $phpPatch >= max($currentVersion)){
                return true;
            }
            else {
                return false;
            }
        }

        return -1;
    }

    public function getPhpVersionArray()
    {
        $version = false;
        if (defined('PHP_VERSION')) {
            $version = explode('.', PHP_VERSION);
        }
        elseif (function_exists('phpversion')){
            $version = explode('.', phpversion());
        }

        return $version;
    }

    /**
     * @return array|bool
     */
    public function getMagentoVersionArray()
    {
        $version = false;
        $currentVersion = $this->softwareData->getProductMetaData()->getVersion();

        if (isset($currentVersion)) {
            $version = explode('.', $currentVersion);
        }

        return $version;
    }

    /**
     * @return array|bool
     */
    public function getMagentoVersionTidyString()
    {
        $magentoVersion = $this->getMagentoVersionArray();

        if (is_array($magentoVersion)) {
            return $magentoVersion[0] . '.' . $magentoVersion[1];
        }

        return false;
    }
}
