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

namespace Buckaroo\Magento2\Model\Idin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Makes sure a quote that needs iDIN age verification is only turned into an order (or paid
 * with a giftcard/voucher) once the verification is done, whatever route the order takes.
 */
class VerificationGuard
{
    /**
     * Quote flag for order creation that continues an already checked payment
     * (giftcard covering the whole order, group transaction push)
     */
    public const FLAG_ALREADY_CHECKED = 'buckaroo_idin_already_checked';

    /**
     * @var IdinConfigProvider
     */
    private $idinConfigProvider;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param IdinConfigProvider      $idinConfigProvider
     * @param State                   $appState
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        IdinConfigProvider $idinConfigProvider,
        State $appState,
        BuckarooLoggerInterface $logger
    ) {
        $this->idinConfigProvider = $idinConfigProvider;
        $this->appState = $appState;
        $this->logger = $logger;
    }

    /**
     * Throw when the quote needs an iDIN verification that has not been done
     *
     * @param CartInterface $quote
     *
     * @throws LocalizedException
     *
     * @return void
     */
    public function assertVerified(CartInterface $quote): void
    {
        if (!$quote instanceof Quote
            || $quote->getData(self::FLAG_ALREADY_CHECKED)
            || !$this->isCustomerFacingArea()
        ) {
            return;
        }

        try {
            $status = $this->idinConfigProvider->getQuoteIdinStatus($quote);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[iDIN] | [Model] | [%s:%s] - Could not determine iDIN status for quote %s | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $quote->getId(),
                $e->getMessage()
            ));
            throw new LocalizedException(
                __('We could not check the age verification for your order. Please try again.')
            );
        }

        if (!empty($status['active'])) {
            $this->logger->addDebug(sprintf(
                '[iDIN] | [Model] | [%s:%s] - Order for quote %s refused, iDIN verification required',
                __METHOD__,
                __LINE__,
                $quote->getId()
            ));
            throw new LocalizedException(
                __('Please verify your age with iDIN before placing this order.')
            );
        }
    }

    /**
     * Orders created by the merchant in the admin are not verified by the shopper
     *
     * @return bool
     */
    private function isCustomerFacingArea(): bool
    {
        try {
            return $this->appState->getAreaCode() !== Area::AREA_ADMINHTML;
        } catch (LocalizedException $e) {
            // No area: command line script, not a shopper
            return false;
        }
    }
}
