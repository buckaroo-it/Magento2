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

namespace Buckaroo\Magento2\Block\Frontend;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\Template;

class ThemeBodyClass extends Template
{
    /**
     * @var Context
     */
    private $contextCopy;

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(Context $context, array $data = [])
    {
        $this->contextCopy = $context;
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        $this->_design = $this->contextCopy->getDesignPackage();

        $themeCode = $this->_design->getDesignTheme()->getCode();
        $cssClass = preg_replace('/\W+/', '-', strtolower(strip_tags($themeCode)));

        $this->pageConfig->addBodyClass($cssClass);

        return parent::_prepareLayout();
    }
}
