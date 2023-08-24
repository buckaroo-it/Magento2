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

namespace Buckaroo\Magento2\Controller\Adminhtml\Notification;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

class MarkUserNotified extends Action
{
    /**
     * @var FlagManager $flagManager
     */
    private FlagManager $flagManager;

    /**
     * @var LoggerInterface $logger
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param FlagManager $flagManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        FlagManager $flagManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    /**
     * Buckaroo Release notification
     *
     * @return ResultInterface
     */
    public function execute()
    {
        try {
            $responseContent = [
                'success' => $this->flagManager->saveFlag('buckaroo_magento2_view_install_screen', true),
                'error_message' => ''
            ];
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[ReleaseNotification] | [Controller] | [%s] - Failed to set flag release notification | [ERROR]: %s',
                __METHOD__,
                $e->getMessage()
            ));
            $responseContent = [
                'success' => false,
                'error_message' => __('Failed to set flag that user has seen screen')
            ];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($responseContent);
    }
}
