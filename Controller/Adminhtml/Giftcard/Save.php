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

class Save extends \TIG\Buckaroo\Controller\Adminhtml\Giftcard\Index
{
    public function execute()
    {
        $isPost = $this->getRequest()->getPost();

        if ($isPost) {
            $giftcardModel = $this->giftcardFactory->create();
            $giftcardId = $this->getRequest()->getParam('entity_id');

            if ($giftcardId) {
                $giftcardModel->load($giftcardId);
            }

            $formData = $this->getRequest()->getParam('giftcard');
            $giftcardModel->setData($formData);

            try {
                $giftcardModel->save();
                $this->messageManager->addSuccess(__('The giftcard has been saved.'));

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['entity_id' => $giftcardModel->getId(), '_current' => true]);
                    return;
                }

                $this->_redirect('*/*/');
                return;
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
            }

            $this->_getSession()->setFormData($formData);
            $this->_redirect('*/*/edit', ['id' => $giftcardId]);
        }
    }
}
