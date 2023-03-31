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
     * @param Session $checkoutSession
     * @param PaymentGroupTransaction $groupTransaction
     * @param ManagerInterface $messageManager
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
     * @param Cart $subject
     * @param int|Product $productInfo
     * @param DataObject|int|array $requestInfo
     * @return array
     * @throws \Exception
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
     * @throws \Exception
     */
    public function allowedMethod($subject)
    {
        if ($this->getAlreadyPaid($subject->getQuote()) > 0) {
            //phpcs:ignore:Magento2.Exceptions.DirectThrow
            throw new \Exception('Action is blocked, please finish current order');
        }
    }

    /**
     * Get quote already paid amount
     *
     * @param Quote $quote
     * @return float
     */
    private function getAlreadyPaid(Quote $quote)
    {
        return $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
    }

    /**
     * Check if allowed function AddProductsByIds
     *
     * @param Cart $subject
     * @param array $productIds
     * @return array
     * @throws \Exception
     */
    public function beforeAddProductsByIds(Cart $subject, $productIds)
    {
        $this->allowedMethod($subject);

        return [$productIds];
    }

    /**
     * Check if allowed function UpdateItems
     *
     * @param Cart $subject
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function beforeUpdateItems(Cart $subject, $data)
    {
        $this->allowedMethod($subject);

        return [$data];
    }

    /**
     * Check if allowed function UpdateItem
     *
     * @param Cart $subject
     * @param int|array|DataObject $requestInfo
     * @param null|array|DataObject $updatingParams
     * @return array
     * @throws \Exception
     */
    public function beforeUpdateItem(
        Cart $subject,
        $requestInfo = null,
        $updatingParams = null
    ) {
        $this->allowedMethod($subject);

        return [$requestInfo, $updatingParams];
    }

    /**
     * Check if allowed function
     *
     * @param Cart $subject
     * @param int $itemId
     * @return int[]|array
     * @throws \Exception
     */
    public function beforeRemoveItem(Cart $subject, $itemId)
    {
        $this->allowedMethod($subject);

        return [$itemId];
    }
}
