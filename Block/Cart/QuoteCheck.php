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

use \Magento\Framework\Message\ManagerInterface;

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
     * Plugin constructor.
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        ManagerInterface $messageManager
    ) {
        $this->quote           = $checkoutSession->getQuote();
        $this->_messageManager = $messageManager;
    }

    /**
     * @param \Magento\Checkout\Model\Cart $subject
     * @param $data
     * @return array
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
     * @param \Magento\Checkout\Model\Cart $subject
     * @param array $productIds
     * @return array
     * @throws \Exception
     */
    public function beforeAddProductsByIds(\Magento\Checkout\Model\Cart $subject, $productIds)
    {
        $this->allowedMethod($subject);

        return [$productIds];
    }

    /**
     * Check if allowed function UpdateItems
     *
     * @param \Magento\Checkout\Model\Cart $subject
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function beforeUpdateItems(\Magento\Checkout\Model\Cart $subject, $data)
    {
        $this->allowedMethod($subject);

        return [$data];
    }

    /**
     * Check if allowed function UpdateItem
     *
     * @param \Magento\Checkout\Model\Cart $subject
     * @param int|array|\Magento\Framework\DataObject $requestInfo
     * @param null|array|\Magento\Framework\DataObject $updatingParams
     * @return array
     * @throws \Exception
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
     * @param \Magento\Checkout\Model\Cart $subject
     * @param int $itemId
     * @return int
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
     * @throws \Exception
     */
    public function allowedMethod($subject)
    {
        $quote = $subject->getQuote();

        if ($quote->getBaseBuckarooAlreadyPaid() > 0) {
            //phpcs:ignore:Magento2.Exceptions.DirectThrow
            throw new \Exception('Action is blocked, please finish current order');
        }
    }
}
