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

namespace Buckaroo\Magento2\Block\Adminhtml\Config\Support;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\BlockInterface;

class BodyClass extends Template implements BlockInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param Context $context
     * @param array   $data
     */
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);

        $this->request = $context->getRequest();

        if ($this->request->getParam('section') == 'buckaroo_magento2') {
            $this->pageConfig->addBodyClass('buckaroo-config-page');
        }
    }
}
