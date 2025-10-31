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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\SecondChanceRepository;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;

class SecondChance extends Action
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var SecondChanceRepository
     */
    protected $secondChanceRepository;

    /**
     * @param Context $context
     * @param Log                                   $logger
     * @param SecondChanceRepository                $secondChanceRepository
     */
    public function __construct(
        Context $context,
        Log $logger,
        SecondChanceRepository $secondChanceRepository
    ) {
        parent::__construct($context);
        $this->logger                 = $logger;
        $this->secondChanceRepository = $secondChanceRepository;
    }

    /**
     * Process action
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function execute()
    {
        if ($token = $this->getRequest()->getParam('token')) {
            try {
                $this->secondChanceRepository->getSecondChanceByToken($token);
                $this->messageManager->addSuccessMessage(__('Your cart has been restored. You can now complete your purchase.'));
            } catch (Exception $e) {
                $this->logger->addError('SecondChance token error: ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('Invalid or expired link. Please try again.'));
            }
        } else {
            $this->messageManager->addErrorMessage(__('Invalid link. Please try again.'));
        }

        return $this->handleRedirect('checkout', ['_fragment' => 'payment']);
    }

    public function handleRedirect($path, $arguments = [])
    {
        return $this->_redirect($path, $arguments);
    }
}
