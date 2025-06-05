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


    public function __construct(
        PaymentGroupTransaction $groupTransaction,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->groupTransaction = $groupTransaction;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param CouponManagementInterface $subject
     * @param int $cartId
     * @param string $couponCode
     * @return array
     * @throws CouldNotSaveException
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
     * @param CouponManagementInterface $subject
     * @param int $cartId
     * @return array
     * @throws CouldNotDeleteException
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

    private function isGroupTransaction($cartId): bool
    {
         /** @var  \Magento\Quote\Model\Quote $quote */
         $quote = $this->quoteRepository->getActive($cartId);
        return $this->groupTransaction->getAlreadyPaid($quote->getReservedOrderId()) > 0;
    }
}
