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

namespace Buckaroo\Magento2\Block\Cart;

use Magento\Checkout\Model\Cart;
use Magento\Framework\Message\ManagerInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Quote\Model\Quote;

class QuoteCheck
{
    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * Plugin constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param PaymentGroupTransaction         $groupTransaction
     * @param ManagerInterface                $messageManager
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        PaymentGroupTransaction $groupTransaction,
        ManagerInterface $messageManager
    ) {
        $this->quote           = $checkoutSession->getQuote();
        $this->groupTransaction = $groupTransaction;
        $this->_messageManager = $messageManager;
    }

    /**
     * @param Cart $subject
     * @param mixed $productInfo
     * @param null|mixed $requestInfo
     * @return array
     * @throws \Exception
     */

    public function beforeAddProduct(
        \Magento\Checkout\Model\Cart $subject,
        $productInfo,
        $requestInfo = null
    ) {
        $this->allowedMethod($subject);

        return [$productInfo, $requestInfo];
    }

    /**
     * Check if allowed function AddProductsByIds
     *
     * @param  \Magento\Checkout\Model\Cart $subject
     * @param  array                        $productIds
     * @throws \Exception
     * @return array
     */
    public function beforeAddProductsByIds(\Magento\Checkout\Model\Cart $subject, $productIds)
    {
        $this->allowedMethod($subject);

        return [$productIds];
    }

    /**
     * Check if allowed function UpdateItems
     *
     * @param  \Magento\Checkout\Model\Cart $subject
     * @param  array                        $data
     * @throws \Exception
     * @return array
     */
    public function beforeUpdateItems(\Magento\Checkout\Model\Cart $subject, $data)
    {
        $this->allowedMethod($subject);

        return [$data];
    }

    /**
     * Check if allowed function UpdateItem
     *
     * @param  \Magento\Checkout\Model\Cart             $subject
     * @param  int|array|\Magento\Framework\DataObject  $requestInfo
     * @param  null|array|\Magento\Framework\DataObject $updatingParams
     * @throws \Exception
     * @return array
     */
    public function beforeUpdateItem(
        \Magento\Checkout\Model\Cart $subject,
        $requestInfo = null,
        $updatingParams = null
    ) {
        $this->allowedMethod($subject);

        return [$requestInfo, $updatingParams];
    }

    /**
     * Check if allowed function
     *
     * @param  \Magento\Checkout\Model\Cart $subject
     * @param  int                          $itemId
     * @return int[]
     * @throws \Exception
     */
    public function beforeRemoveItem(\Magento\Checkout\Model\Cart $subject, $itemId)
    {
        $this->allowedMethod($subject);

        return [$itemId];
    }

    /**
     * Blocks method if start group transaction
     *
     * @param  mixed      $subject
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
     * Get quote already payed amount
     *
     * @param Quote $quote
     *
     * @return float
     */
    private function getAlreadyPaid(Quote $quote)
    {
        return $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId());
    }
}
