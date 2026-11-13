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

namespace Buckaroo\Magento2\Controller\Adminhtml\Giftcard;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;

class Delete extends Index implements HttpPostActionInterface
{
    /**
     * Delete Giftcard
     *
     * @return Page|ResponseInterface
     */
    public function execute()
    {
        $giftcardId = $this->getRequest()->getParam('entity_id');

        if ($giftcardId) {
            $giftcardModel = $this->giftcardFactory->create();
            $this->giftcardResource->load($giftcardModel, $giftcardId);

            if (!$giftcardModel->getId()) {
                $this->messageManager->addErrorMessage(__('This giftcard no longer exists.'));
            } else {
                try {
                    $this->giftcardResource->delete($giftcardModel);
                    $this->messageManager->addSuccessMessage(__('The giftcard has been deleted.'));

                    return $this->_redirect('*/*/');
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                    return $this->_redirect('*/*/edit', ['id' => $giftcardModel->getId()]);
                }
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a Giftcard to delete.'));
        return $this->_redirect('*/*/');
    }
}
