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
use Magento\Checkout\Model\Session as CheckoutSession;

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
     * @return ResponseInterface
     */
    public function execute()
    {
        if ($token = $this->getRequest()->getParam('token')) {
            try {
                $secondChance = $this->secondChanceRepository->getSecondChanceByToken($token);
                
                // Verify quote was properly set in session
                $quote = $this->checkoutSession->getQuote();
                
                if (!$quote || !$quote->getId()) {
                    $this->logger->addError('SecondChance: No quote in session after restoration', [
                        'token' => substr($token, 0, 8) . '...',
                        'order_id' => $secondChance->getOrderId()
                    ]);
                    $this->messageManager->addErrorMessage(__('Unable to restore your cart. Please try again or contact support.'));
                    return $this->handleRedirect('checkout/cart');
                }
                
                // Log quote details for debugging
                $this->logger->addDebug('SecondChance: Quote restored successfully', [
                    'quote_id' => $quote->getId(),
                    'order_id' => $secondChance->getOrderId(),
                    'reserved_order_id' => $quote->getReservedOrderId(),
                    'items_count' => $quote->getItemsCount(),
                    'payment_method' => $quote->getPayment()->getMethod(),
                    'has_billing' => $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : 'no',
                    'has_shipping' => $quote->getShippingAddress() ? $quote->getShippingAddress()->getCountryId() : 'no',
                    'shipping_method' => $quote->getShippingAddress() ? $quote->getShippingAddress()->getShippingMethod() : 'no'
                ]);
                
                $this->messageManager->addSuccessMessage(__('Your cart has been restored. You can now complete your purchase.'));
            } catch (Exception $e) {
                $this->logger->addError('SecondChance token error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'token' => $token ? substr($token, 0, 8) . '...' : 'none'
                ]);
                $this->messageManager->addErrorMessage(__('Invalid or expired link. Please try again.'));
                return $this->handleRedirect('checkout/cart');
            }
        } else {
            $this->logger->addWarning('SecondChance: No token provided');
            $this->messageManager->addErrorMessage(__('Invalid link. Please try again.'));
            return $this->handleRedirect('checkout/cart');
        }

        return $this->handleRedirect('checkout', ['_fragment' => 'payment']);
    }

    public function handleRedirect($path, $arguments = [])
    {
        return $this->_redirect($path, $arguments);
    }
}
