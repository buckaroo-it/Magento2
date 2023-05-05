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
            $giftcardModel->load($giftcardId);

            if (!$giftcardModel->getId()) {
                $this->messageManager->addErrorMessage(__('This giftcard no longer exists.'));
            } else {
                try {
                    $giftcardModel->delete();
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
