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
namespace TIG\Buckaroo\Controller\Adminhtml\Giftcard;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use TIG\Buckaroo\Model\GiftcardFactory;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var  PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var  Registry
     */
    protected $_coreRegistry;

    /**
     * @var  GiftcardFactory
     */
    protected $giftcardFactory;

    /**
     * @param Context         $context
     * @param Registry        $coreRegistry
     * @param PageFactory     $resultPageFactory
     * @param GiftcardFactory $giftcardFactory
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        PageFactory $resultPageFactory,
        GiftcardFactory $giftcardFactory
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->giftcardFactory = $giftcardFactory;
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /**
         * @var \Magento\Backend\Model\View\Result\Page $resultPage
         */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('TIG_Buckaroo::buckaroo_giftcards');
        $resultPage->getConfig()->getTitle()->prepend(__('Buckaroo Giftcards'));

        return $resultPage;
    }

    /**
     * Is the user allowed to view the blog post grid.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('TIG_Buckaroo::buckaroo_giftcards');
    }
}
