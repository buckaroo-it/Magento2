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
     * @param Context                       $context
     * @param PageFactory                   $resultPageFactory
     * @param GiftcardFactory               $giftcardFactory
     * @param BuckarooGiftcardDataInterface $buckarooGiftcardData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GiftcardFactory $giftcardFactory,
        BuckarooGiftcardDataInterface $buckarooGiftcardData
    ) {
        parent::__construct($context);

        $this->buckarooGiftcardData = $buckarooGiftcardData;
        $this->resultPageFactory = $resultPageFactory;
        $this->giftcardFactory = $giftcardFactory;
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
            $model->load($giftcardId);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This giftcard no longer exists.'));
                return $this->_redirect('*/*/');
            }
        }

        $data = $this->_session->getFormData(true);
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
