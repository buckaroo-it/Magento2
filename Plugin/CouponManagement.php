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

namespace Buckaroo\Magento2\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use \Magento\Quote\Api\CouponManagementInterface;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;

class CouponManagement
{

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * Constructor.
     *
     * @param PaymentGroupTransaction $groupTransaction
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        PaymentGroupTransaction $groupTransaction,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->groupTransaction = $groupTransaction;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Block setting a coupon while a group transaction is in progress.
     *
     * @param CouponManagementInterface $subject
     * @param int                       $cartId
     * @param string                    $couponCode
     *
     * @throws CouldNotSaveException
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSet(CouponManagementInterface $subject, $cartId, $couponCode)
    {
        if ($this->isGroupTransaction($cartId)) {
            throw new CouldNotSaveException(
                __("Action is blocked, please finish current order")
            );
        }
        return [$cartId, $couponCode];
    }

    /**
     * Block removing a coupon while a group transaction is in progress.
     *
     * @param CouponManagementInterface $subject
     * @param int                       $cartId
     *
     * @throws CouldNotDeleteException
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeRemove(CouponManagementInterface $subject, $cartId)
    {
        if ($this->isGroupTransaction($cartId)) {
            throw new CouldNotDeleteException(
                __("Action is blocked, please finish current order")
            );
        }
        return [$cartId];
    }

    /**
     * Determine whether the active quote has an already paid group transaction.
     *
     * @param int $cartId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function isGroupTransaction($cartId): bool
    {
         /** @var  \Magento\Quote\Model\Quote $quote */
         $quote = $this->quoteRepository->getActive($cartId);
        return $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId()) > 0;
    }
}
