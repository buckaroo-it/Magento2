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
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin\QuoteManagement;

use Buckaroo\Magento2\Model\Idin\VerificationGuard;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Checks the iDIN age verification at the point every checkout route (Luma, Hyvä, REST,
 * GraphQL, express wallets) turns a quote into an order.
 */
class IdinVerification
{
    /**
     * @var VerificationGuard
     */
    private $verificationGuard;

    /**
     * @param VerificationGuard $verificationGuard
     */
    public function __construct(VerificationGuard $verificationGuard)
    {
        $this->verificationGuard = $verificationGuard;
    }

    /**
     * Refuse the order when the quote still needs iDIN verification
     *
     * @param QuoteManagement $subject
     * @param Quote           $quote
     * @param array           $orderData
     *
     * @throws LocalizedException
     *
     * @return null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSubmit(QuoteManagement $subject, $quote, $orderData = [])
    {
        $this->verificationGuard->assertVerified($quote);

        return null;
    }
}
