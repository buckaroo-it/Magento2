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
     */
    public function formatPrice(float $amount): string
    {
        return $this->priceHelper->currency($amount, true, false);
    }

    /**
     * Get last real order from checkout session.
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
