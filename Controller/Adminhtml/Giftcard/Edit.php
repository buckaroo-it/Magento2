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

use Buckaroo\Magento2\Model\Data\BuckarooGiftcardDataInterface;
use Buckaroo\Magento2\Model\Giftcard;
use Buckaroo\Magento2\Model\GiftcardFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var GiftcardFactory
     */
    protected $giftcardFactory;

    /**
     * @var BuckarooGiftcardDataInterface
     */
    private $buckarooGiftcardData;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\Giftcard
     */
    private $giftcardResource;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param GiftcardFactory $giftcardFactory
     * @param BuckarooGiftcardDataInterface $buckarooGiftcardData
     * @param \Buckaroo\Magento2\Model\ResourceModel\Giftcard $giftcardResource
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GiftcardFactory $giftcardFactory,
        BuckarooGiftcardDataInterface $buckarooGiftcardData,
        \Buckaroo\Magento2\Model\ResourceModel\Giftcard $giftcardResource
    ) {
        parent::__construct($context);

        $this->buckarooGiftcardData = $buckarooGiftcardData;
        $this->resultPageFactory = $resultPageFactory;
        $this->giftcardFactory = $giftcardFactory;
        $this->giftcardResource = $giftcardResource;
    }

    /**
     * Edit Giftcard
     *
     * @throws LocalizedException
     *
     * @return ResponseInterface|Page
     */
    public function execute()
    {
        $giftcardId = $this->getRequest()->getParam('entity_id');

        /**
         * @var Giftcard $model
         */
        $model = $this->giftcardFactory->create();

        if ($giftcardId) {
            $this->giftcardResource->load($model, $giftcardId);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This giftcard no longer exists.'));
                return $this->_redirect('*/*/');
            }
        }

        $data = $this->_session->getFormData();
        if (!empty($data)) {
            $model->setData($data);
        }

        $this->buckarooGiftcardData->setGiftcardModel($model);

        /**
         * @var Page $resultPage
         */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Buckaroo_Magento2::buckaroo_giftcards');
        $resultPage->getConfig()->getTitle()->prepend(__('Buckaroo Giftcards'));

        return $resultPage;
    }
}
