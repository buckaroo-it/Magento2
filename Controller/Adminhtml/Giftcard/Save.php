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

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;

class Save extends Index implements HttpPostActionInterface
{
    /**
     * Save Giftcard in Admin
     *
     * @return ResponseInterface|void
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPost();
        if ($isPost) {
            $giftcardModel = $this->giftcardFactory->create();
            $giftcardId = $this->getRequest()->getParam('entity_id');

            if ($giftcardId) {
                $this->giftcardResource->load($giftcardModel, $giftcardId);
            }

            $formData = $this->getFormData();
            $giftcardModel->setData($formData);

            try {
                $this->giftcardResource->save($giftcardModel);
                $this->messageManager->addSuccess(__('The giftcard has been saved.'));

                if ($this->getRequest()->getParam('back')) {
                    return $this->_redirect('*/*/edit', ['entity_id' => $giftcardModel->getId(), '_current' => true]);
                }

                return $this->_redirect('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
            }

            $this->_getSession()->setFormData($formData);
            return $this->_redirect('*/*/edit', ['id' => $giftcardId]);
        }
    }

    /**
     * Return form data
     *
     * @return array
     */
    private function getFormData()
    {
        $formData = $this->getRequest()->getParam('giftcard');
        $filesData = $this->getRequest()->getFiles('logo');

        if ((isset($filesData['name'])) && ($filesData['name'] != '') && (!isset($formData['logo']['delete']))) {
            try {
                $uploaderFactory = $this->uploaderFactory->create(['fileId' => 'logo']);
                $uploaderFactory->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                $uploaderFactory->setAllowRenameFiles(true);
                $uploaderFactory->setFilesDispersion(true);
                $mediaDirectory = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA);
                $destinationPath = $mediaDirectory->getAbsolutePath('buckaroo');
                $result = $uploaderFactory->save($destinationPath);

                if (!$result) {
                    throw new LocalizedException(__('File cannot be saved to path: $1', $destinationPath));
                }

                $formData['logo'] = 'buckaroo' . $result['file'];
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        if (isset($formData['logo']['delete'])) {
            $formData['logo'] = '';
        }

        return $formData;
    }
}
