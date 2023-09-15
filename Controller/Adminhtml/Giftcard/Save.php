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
                $giftcardModel->load($giftcardId);
            }

            $formData = $this->getFormData();
            $giftcardModel->setData($formData);

            try {
                $giftcardModel->save();
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
