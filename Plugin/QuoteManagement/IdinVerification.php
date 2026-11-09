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
