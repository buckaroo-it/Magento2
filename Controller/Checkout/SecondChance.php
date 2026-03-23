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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

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
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param Context                $context
     * @param Log                    $logger
     * @param SecondChanceRepository $secondChanceRepository
     * @param CheckoutSession        $checkoutSession
     */
    public function __construct(
        Context $context,
        Log $logger,
        SecondChanceRepository $secondChanceRepository,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->logger                 = $logger;
        $this->secondChanceRepository = $secondChanceRepository;
        $this->checkoutSession        = $checkoutSession;
    }

    /**
     * Process action
     *
     * @throws Exception
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        if ($token = $this->getRequest()->getParam('token')) {
            try {
                $this->secondChanceRepository->getSecondChanceByToken($token);

                // Verify quote was properly set in session
                $quote = $this->checkoutSession->getQuote();

                if (!$quote->getId()) {
                    $this->logger->addError('SecondChance: No quote in session after restoration');
                    $this->messageManager->addErrorMessage(__('Unable to restore your cart. Please try again or contact support.'));
                    return $this->handleRedirect('checkout/cart');
                }

                $this->messageManager->addSuccessMessage(__('Your cart has been restored. You can now complete your purchase.'));
            } catch (Exception $e) {
                $this->logger->addWarning('SecondChance: invalid or expired token');
                $this->messageManager->addErrorMessage(__('Invalid or expired link. Please try again.'));
                return $this->handleRedirect('checkout/cart');
            }
        } else {
            $this->logger->addWarning('SecondChance: No token provided');
            $this->messageManager->addErrorMessage(__('Invalid link. Please try again.'));
            return $this->handleRedirect('checkout/cart');
        }

        $queryParams = $this->getRequest()->getParams();
        unset($queryParams['token']);

        return $this->handleRedirect('checkout', [
            '_query'    => $queryParams,
            '_fragment' => 'payment',
        ]);
    }

    /**
     * Handle redirect to specified path with arguments
     *
     * @param string $path
     * @param array $arguments
     * @return Redirect
     */
    public function handleRedirect($path, $arguments = []): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath($path, $arguments);
    }
}
