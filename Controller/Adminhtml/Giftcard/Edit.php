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

class Edit extends \TIG\Buckaroo\Controller\Adminhtml\Giftcard\Index
{
    /**
     * @return \Magento\Backend\Model\View\Result\Page|void
     */
    public function execute()
    {
        $giftcardId = $this->getRequest()->getParam('entity_id');

        /**
         * @var \TIG\Buckaroo\Model\Giftcard $model
         */
        $model = $this->giftcardFactory->create();

        if ($giftcardId) {
            $model->load($giftcardId);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This giftcard no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        $data = $this->_session->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }
        $this->_coreRegistry->register('buckaroo_giftcard', $model);

        /**
         * @var \Magento\Backend\Model\View\Result\Page $resultPage
         */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('TIG_Buckaroo::buckaroo_giftcards');
        $resultPage->getConfig()->getTitle()->prepend(__('Buckaroo Giftcards'));

        return $resultPage;
    }
}
