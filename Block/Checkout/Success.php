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

namespace Buckaroo\Magento2\Block\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Sales\Model\Order\Config;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Sales\Api\Data\OrderInterface;

class Success extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @param TemplateContext $context
     * @param Session         $checkoutSession
     * @param Config          $orderConfig
     * @param HttpContext     $httpContext
     * @param CurrentCustomer $currentCustomer
     * @param PriceHelper     $priceHelper
     * @param array           $data
     */
    public function __construct(
        TemplateContext $context,
        Session $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        CurrentCustomer $currentCustomer,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $checkoutSession,
            $orderConfig,
            $httpContext,
            $data
        );
        $this->checkoutSession = $checkoutSession;
        $this->currentCustomer = $currentCustomer;
        $this->priceHelper = $priceHelper;
    }

    /**
     * Check whether last order was placed with Buckaroo Transfer.
     */
    public function isTransferPayment(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }

        return $payment->getMethod() === 'buckaroo_magento2_transfer';
    }

    /**
     * Check if transfer payment instructions should be shown on success page.
     */
    public function shouldDisplayTransferInstructions(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return false;
        }

        $methodInstance = $payment->getMethodInstance();
        if (!$methodInstance) {
            return true;
        }

        $showInstructions = $methodInstance->getConfigData('display_payment_instructions_success', $order->getStoreId());

        return $showInstructions === null || $showInstructions === '' || (bool)$showInstructions;
    }

    /**
     * Return transfer details from payment additional information if available.
     *
     * @return array
     */
    public function getTransferDetails(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return [];
        }

        $details = $payment->getAdditionalInformation('transfer_details');
        if (!is_array($details)) {
            return [];
        }

        return $details;
    }

    /**
     * Format price in order currency.
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        return $this->priceHelper->currency($amount, true, false);
    }

    /**
     * Get the last real order from the checkout session.
     */
    public function getOrder(): ?OrderInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId()) {
            return $order;
        }
        return null;
    }
}
