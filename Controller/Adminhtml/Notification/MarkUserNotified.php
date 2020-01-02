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

namespace TIG\Buckaroo\Controller\Adminhtml\Notification;

use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

class MarkUserNotified extends \Magento\Backend\App\Action
{
    /** @var FlagManager $flagManager */
    private $flagManager;

    /** @var LoggerInterface $logger */
    private $logger;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param FlagManager $flagManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        FlagManager $flagManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        try {
            $responseContent = [
                'success' => $this->flagManager->saveFlag('tig_buckaroo_view_install_screen', true),
                'error_message' => ''
            ];
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $responseContent = [
                'success' => false,
                'error_message' => __('Failed to set flag that user has seen screen')
            ];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($responseContent);
    }
}
