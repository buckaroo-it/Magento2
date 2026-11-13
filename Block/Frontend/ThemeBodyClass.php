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
     * @param array   $data
     */
    public function __construct(Context $context, array $data = [])
    {
        $this->contextCopy = $context;
        parent::__construct($context, $data);
    }

    /**
     * Add the current design theme code as a CSS class to the page body.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->_design = $this->contextCopy->getDesignPackage();

        $themeCode = $this->_design->getDesignTheme()->getCode();
        $cssClass = preg_replace('/\W+/', '-', strtolower(strip_tags($themeCode)));

        $this->pageConfig->addBodyClass($cssClass);

        return parent::_prepareLayout();
    }
}
