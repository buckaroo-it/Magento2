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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

/**
 * Interface PayResponseInterface
 * @api
 */

interface PayResponseInterface
{
    /**
     * Get RemainderAmount
     * 
     * @api
     * @return float
     */
    public function getRemainderAmount();

    /**
     * Get AlreadyPaid
     *
     * @api
     * @return float
     */
    public function getAlreadyPaid();

    /**
     * Get newly created transaction with giftcard name
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface
     */
    public function getTransaction();

    /**
     * Get user message
     *
     * @api
     * @return string|null
     */
    public function getMessage();


     /**
     * Get user remaining amount message
     *
     * @api
     * @return string|null
     */
    public function getRemainingAmountMessage();
}
