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
namespace TIG\Buckaroo\Block\Adminhtml\Config\Support;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\BlockInterface;

class BodyClass extends Template implements BlockInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
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

        if ($this->request->getParam('section') == 'tig_buckaroo') {
            $this->pageConfig->addBodyClass('buckaroo-config-page');
        }
    }
}
