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

namespace Buckaroo\Magento2\Api\Data\Giftcard;

use Magento\Framework\Phrase;

/**
 * Interface PayResponseInterface
 *
 * @api
 */
interface PayResponseInterface
{
    /**
     * Get RemainderAmount
     *
     * @return float
     * @api
     */
    public function getRemainderAmount(): float;

    /**
     * Get AlreadyPaid
     *
     * @return float
     * @api
     */
    public function getAlreadyPaid(): float;

    /**
     * Get newly created transaction with giftcard name
     *
     * @return \Buckaroo\Magento2\Api\Data\Giftcard\TransactionResponseInterface
     */
    public function getTransaction(): TransactionResponseInterface;

    /**
     * Get user message
     *
     * @return Phrase|string|null
     * @api
     */
    public function getMessage();

    /**
     * Get user remaining amount message
     *
     * @return Phrase|string|null
     * @api
     */
    public function getRemainingAmountMessage();
}
