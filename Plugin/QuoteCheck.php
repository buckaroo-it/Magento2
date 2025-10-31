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
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;

class QuoteCheck
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * Plugin constructor.
     *
     * @param Session                 $checkoutSession
     * @param PaymentGroupTransaction $groupTransaction
     * @param ManagerInterface        $messageManager
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Session $checkoutSession,
        PaymentGroupTransaction $groupTransaction,
        ManagerInterface $messageManager
    ) {
        $this->quote = $checkoutSession->getQuote();
        $this->messageManager = $messageManager;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Throw error if user already started a group transaction
     *
     * @param Cart                 $subject
     * @param int|Product          $productInfo
     * @param DataObject|int|array $requestInfo
     *
     * @throws \Exception
     *
     * @return array
     */
    public function beforeAddProduct(
        Cart $subject,
        $productInfo,
        $requestInfo = null
    ): array {
        $this->allowedMethod($subject);

        return [$productInfo, $requestInfo];
    }

    /**
     * Blocks method if start group transaction
     *
     * @param Cart $subject
     *
     * @throws \Exception
     */
    public function allowedMethod(Cart $subject)
    {
        // Only apply restrictions if using Buckaroo payment methods
        $quote = $subject->getQuote();
        if (!$this->isBuckarooPayment($quote)) {
            return;
        }
        
        if ($this->getAlreadyPaid($quote) > 0) {
            //phpcs:ignore:Magento2.Exceptions.DirectThrow
            throw new \Exception('Action is blocked, please finish current order');
        }
    }

    /**
     * Get quote already paid amount
     *
     * @param Quote $quote
     *
     * @return float
     */
    private function getAlreadyPaid(Quote $quote): float
    {
        return $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
    }

    /**
     * Check if quote uses Buckaroo payment method
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function isBuckarooPayment(Quote $quote): bool
    {
        $payment = $quote->getPayment();
        if (!$payment || !$payment->getMethod()) {
            return false;
        }
        
        return strpos($payment->getMethod(), "buckaroo_magento2_") !== false;
    }

    /**
     * Check if allowed function AddProductsByIds
     *
     * @param Cart  $subject
     * @param array $productIds
     *
     * @throws \Exception
     *
     * @return array
     */
    public function beforeAddProductsByIds(Cart $subject, array $productIds): array
    {
        $this->allowedMethod($subject);

        return [$productIds];
    }

    /**
     * Check if allowed function UpdateItems
     *
     * @param Cart  $subject
     * @param array $data
     *
     * @throws \Exception
     *
     * @return array
     */
    public function beforeUpdateItems(Cart $subject, array $data): array
    {
        $this->allowedMethod($subject);

        return [$data];
    }

    /**
     * Check if allowed function UpdateItem
     *
     * @param Cart                  $subject
     * @param int|array|DataObject  $requestInfo
     * @param null|array|DataObject $updatingParams
     *
     * @throws \Exception
     *
     * @return array
     */
    public function beforeUpdateItem(
        Cart $subject,
        $requestInfo = null,
        $updatingParams = null
    ): array {
        $this->allowedMethod($subject);

        return [$requestInfo, $updatingParams];
    }

    /**
     * Check if allowed function
     *
     * @param Cart $subject
     * @param int  $itemId
     *
     * @throws \Exception
     *
     * @return int[]|array
     */
    public function beforeRemoveItem(Cart $subject, int $itemId): array
    {
        $this->allowedMethod($subject);

        return [$itemId];
    }
}
