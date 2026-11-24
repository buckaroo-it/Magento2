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

namespace Buckaroo\Magento2\Model\Idin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Idin as IdinConfigProvider;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Makes sure a quote that needs iDIN age verification is only turned into an order (or paid
 * with a giftcard/voucher) once the verification is done, whatever route the order takes.
 *
 * A store whose checkout has no iDIN step is the exception: the shopper cannot verify there, so the
 * order is not refused, and a log warning and an admin message ask the merchant to update the checkout.
 *
 * Orders placed without a shopper are not checked either: admin orders, cron jobs, command line scripts
 * and web API calls made with an integration or admin token (marketplace and ERP imports).
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
     * @var CheckoutSupport
     */
    private $checkoutSupport;

    /**
     * @var UserContextInterface
     */
    private $userContext;

    /**
     * @param IdinConfigProvider      $idinConfigProvider
     * @param State                   $appState
     * @param BuckarooLoggerInterface $logger
     * @param CheckoutSupport         $checkoutSupport
     * @param UserContextInterface    $userContext
     */
    public function __construct(
        IdinConfigProvider $idinConfigProvider,
        State $appState,
        BuckarooLoggerInterface $logger,
        CheckoutSupport $checkoutSupport,
        UserContextInterface $userContext
    ) {
        $this->idinConfigProvider = $idinConfigProvider;
        $this->appState = $appState;
        $this->logger = $logger;
        $this->checkoutSupport = $checkoutSupport;
        $this->userContext = $userContext;
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
            if ($this->isIdinStepMissing($quote)) {
                return;
            }
            throw new LocalizedException(
                __('We could not check the age verification for your order. Please try again.')
            );
        }

        if (!empty($status['active'])) {
            if ($this->isIdinStepMissing($quote)) {
                return;
            }
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
     * Whether the checkout of the quote's store has no iDIN step, logged as a warning for the merchant
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function isIdinStepMissing(Quote $quote): bool
    {
        $checkout = $this->checkoutSupport->getCheckoutWithoutIdinStep($quote->getStoreId());
        if ($checkout === null) {
            return false;
        }

        $this->logger->addWarning(sprintf(
            '[iDIN] | [Model] | [%s:%s] - Quote %s needs iDIN verification, but the %s of store %s has no iDIN'
            . ' step, so the quote is not refused. %s',
            __METHOD__,
            __LINE__,
            $quote->getId(),
            $checkout,
            $quote->getStoreId(),
            $checkout === CheckoutSupport::HYVA_CHECKOUT
                ? 'Update the Buckaroo Hyvä Checkout module to 2.4.0 or higher.'
                : 'Use a checkout with an iDIN step.'
        ));

        return true;
    }

    /**
     * Whether a shopper places the order, so the shopper can be asked to verify
     *
     * Admin orders, cron jobs, command line scripts and web API calls authenticated with an
     * integration or admin token (marketplace and ERP imports) have no shopper to verify.
     *
     * @return bool
     */
    private function isCustomerFacingArea(): bool
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            // No area: command line script, not a shopper
            return false;
        }

        if ($areaCode === Area::AREA_ADMINHTML || $areaCode === Area::AREA_CRONTAB) {
            return false;
        }

        if (in_array($areaCode, [Area::AREA_WEBAPI_REST, Area::AREA_WEBAPI_SOAP, Area::AREA_GRAPHQL], true)) {
            return !in_array(
                (int)$this->userContext->getUserType(),
                [UserContextInterface::USER_TYPE_INTEGRATION, UserContextInterface::USER_TYPE_ADMIN],
                true
            );
        }

        return true;
    }
}
