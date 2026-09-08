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

namespace Buckaroo\Magento2\Service\Giftcard;

use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Caps the number of failed giftcard (card number + pin) attempts per quote.
 *
 * The counter lives on the quote payment so it survives across requests and works for both the
 * storefront ajax controller and the guest webapi, neither of which shares a stable browser
 * session for the API path. It is defense-in-depth against giftcard PIN brute forcing; it does
 * not replace gateway-side controls and can be reset by starting a brand new cart.
 */
class AttemptLimit
{
    /**
     * Maximum number of failed giftcard attempts allowed per quote.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * Payment additional_information key holding the failed-attempt counter.
     */
    private const ATTEMPTS_KEY = 'buckaroo_giftcard_failed_attempts';

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Reject the request when the failed-attempt limit for this quote has been reached.
     *
     * @param Quote $quote
     *
     * @throws ApiException
     *
     * @return void
     */
    public function assertWithinLimit(Quote $quote): void
    {
        if ($this->getFailedAttempts($quote) >= self::MAX_ATTEMPTS) {
            throw new ApiException(
                (string)__('Too many giftcard attempts. Please try again later.')
            );
        }
    }

    /**
     * Record a failed giftcard attempt against the quote.
     *
     * @param Quote $quote
     *
     * @return void
     */
    public function registerFailedAttempt(Quote $quote): void
    {
        $quote->getPayment()->setAdditionalInformation(
            self::ATTEMPTS_KEY,
            $this->getFailedAttempts($quote) + 1
        );
        $this->cartRepository->save($quote);
    }

    /**
     * Current failed-attempt count stored on the quote payment.
     *
     * @param Quote $quote
     *
     * @return int
     */
    private function getFailedAttempts(Quote $quote): int
    {
        return (int)$quote->getPayment()->getAdditionalInformation(self::ATTEMPTS_KEY);
    }
}
