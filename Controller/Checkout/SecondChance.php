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

class SecondChance extends \Magento\Framework\App\Action\Action
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
     * @param \Magento\Framework\App\Action\Context $context
     * @param Log                                   $logger
     * @param SecondChanceRepository                $secondChanceRepository
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
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
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        if ($token = $this->getRequest()->getParam('token')) {
            try {
                $this->secondChanceRepository->getSecondChanceByToken($token);
                $this->messageManager->addSuccessMessage(__('Your cart has been restored. You can now complete your purchase.'));
            } catch (\Exception $e) {
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