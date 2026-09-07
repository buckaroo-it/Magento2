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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ResourceModel\SecondChance\Collection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SecondChance
{
    public const XPATH_SECOND_CHANCE_ENABLED = 'buckaroo_magento2/second_chance/enable_second_chance';
    public const XPATH_SECOND_CHANCE_EMAIL1_ENABLED = 'buckaroo_magento2/second_chance/second_chance_email';
    public const XPATH_SECOND_CHANCE_EMAIL2_ENABLED = 'buckaroo_magento2/second_chance/second_chance_email2';
    public const XPATH_SECOND_CHANCE_TEMPLATE1 = 'buckaroo_magento2/second_chance/second_chance_template';
    public const XPATH_SECOND_CHANCE_TEMPLATE2 = 'buckaroo_magento2/second_chance/second_chance_template2';
    public const XPATH_SECOND_CHANCE_TIMING1 = 'buckaroo_magento2/second_chance/second_chance_timing';
    public const XPATH_SECOND_CHANCE_TIMING2 = 'buckaroo_magento2/second_chance/second_chance_timing2';
    public const XPATH_NO_SEND_OUT_OF_STOCK = 'buckaroo_magento2/second_chance/no_send_second_chance';
    public const XPATH_PRUNE_DAYS = 'buckaroo_magento2/second_chance/prune_days';
    public const XPATH_MULTIPLE_EMAILS_SEND = 'buckaroo_magento2/second_chance/multiple_emails_send';
    public const XPATH_STREAK_ENABLED = 'buckaroo_magento2/second_chance/streak_enabled';
    public const XPATH_STREAK_MINUTES = 'buckaroo_magento2/second_chance/streak_minutes';
    public const XPATH_PAID_ORDER_CHECK = 'buckaroo_magento2/second_chance/paid_order_check';
    public const XPATH_GIFT_CARD_INVALID_MESSAGE = 'buckaroo_magento2/second_chance/gift_card_invalid_message';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if SecondChance is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isSecondChanceEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if first email is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isFirstEmailEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_EMAIL1_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if second email is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isSecondEmailEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_EMAIL2_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get first email template
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getFirstEmailTemplate($store = null): string
    {
        $template = (string) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TEMPLATE1,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        // Force correct template ID if it's set to an incorrect value
        if ($template === 'buckaroo_second_chance' || empty($template)) {
            $result = 'buckaroo_second_chance_first';
        } else {
            $result = $template;
        }

        return $result;
    }

    /**
     * Get second email template
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSecondEmailTemplate($store = null): string
    {
        $template = (string) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TEMPLATE2,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        // Force correct template ID if it's set to an incorrect value
        if ($template === 'buckaroo_second_chance' || empty($template)) {
            return 'buckaroo_second_chance_second';
        } else {
            return $template;
        }
    }

    /**
     * Get first email timing in hours
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getFirstEmailTiming($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TIMING1,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get second email timing in hours
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getSecondEmailTiming($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_SECOND_CHANCE_TIMING2,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if sending should be skipped for out of stock products
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function shouldSkipOutOfStock($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_NO_SEND_OUT_OF_STOCK,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get prune days setting
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getPruneDays($store = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XPATH_PRUNE_DAYS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Check if multiple emails can be sent at once
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function canSendMultipleEmails($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_MULTIPLE_EMAILS_SEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get SecondChance delete after days (alias for getPruneDays)
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getSecondChanceDeleteAfterDays($store = null): int
    {
        return $this->getPruneDays($store);
    }

    /**
     * Check whether pruning of old SecondChance records is configured for a store.
     *
     * A retention window of zero-days disables pruning altogether.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isRecordPruningEnabled($store = null): bool
    {
        return $this->getSecondChanceDeleteAfterDays($store) > 0;
    }

    /**
     * Return the combined delay of the reminders that can still go out for a store.
     *
     * Used to make sure record pruning never removes a record before its reminders had the
     * chance to be sent. Disabled steps contribute nothing, because their records will never
     * advance any further.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int Hours
     */
    public function getReminderWindowHours($store = null): int
    {
        $hours = 0;

        if ($this->isFirstEmailEnabled($store)) {
            $hours += max(0, $this->getFirstEmailTiming($store));
        }

        if ($this->isSecondEmailEnabled($store)) {
            $hours += max(0, $this->getSecondEmailTiming($store));
        }

        return $hours;
    }

    /**
     * Check whether the email belonging to a SecondChance step is enabled for a store.
     *
     * @param int                                             $step
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isEmailStepEnabled($step, $store = null): bool
    {
        if ((int) $step === Collection::STEP_FIRST_EMAIL) {
            return $this->isFirstEmailEnabled($store);
        }

        return $this->isSecondEmailEnabled($store);
    }

    /**
     * Get SecondChance delay based on the step
     *
     * @param int                                             $step
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getSecondChanceDelay($step, $store = null): int
    {
        if ((int) $step === Collection::STEP_FIRST_EMAIL) {
            return $this->getFirstEmailTiming($store);
        }
        return $this->getSecondEmailTiming($store);
    }

    /**
     * Get SecondChance email limit
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getSecondChanceEmailLimit($store = null): int
    {
        // Return 0 for no limit, could be configurable later
        return 0;
    }

    /**
     * Check if SecondChance multiple is enabled (alias for canSendMultipleEmails)
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isSecondChanceMultipleEnabled($store = null): bool
    {
        return $this->canSendMultipleEmails($store);
    }

    /**
     * Get SecondChance email template based on step
     *
     * @param int                                             $step
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSecondChanceEmailTemplate($step, $store = null): string
    {
        if ($step == 1) {
            return $this->getFirstEmailTemplate($store);
        }
        return $this->getSecondEmailTemplate($store);
    }

    /**
     * Check if streak de-duplication mode is enabled
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isStreakEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_STREAK_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get a streak window in minutes
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return int
     */
    public function getStreakMinutes($store = null): int
    {
        $minutes = (int) $this->scopeConfig->getValue(
            self::XPATH_STREAK_MINUTES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
        return max(1, $minutes);
    }

    /**
     * Check whether the paid-order validation is enabled.
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return bool
     */
    public function isPaidOrderCheckEnabled($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XPATH_PAID_ORDER_CHECK,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get a configurable message shown when a restored gift card is no longer valid
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getGiftCardInvalidMessage($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XPATH_GIFT_CARD_INVALID_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get SecondChance sender name
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSecondChanceSenderName($store = null): string
    {
        // Try to get configured sender first, fall back to sales
        $senderName = (string) $this->scopeConfig->getValue(
            'buckaroo_magento2/second_chance/sender_name',
            ScopeInterface::SCOPE_STORE,
            $store
        );

        if (empty($senderName)) {
            $senderName = (string) $this->scopeConfig->getValue(
                'trans_email/ident_sales/name',
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }

        // Use default if still empty
        return $senderName ?: 'Buckaroo';
    }

    /**
     * Get SecondChance sender email
     *
     * @param \Magento\Store\Api\Data\StoreInterface|int|null $store
     *
     * @return string
     */
    public function getSecondChanceSenderEmail($store = null): string
    {
        // Try to get configured sender first, fall back to sales
        $senderEmail = (string) $this->scopeConfig->getValue(
            'buckaroo_magento2/second_chance/sender_email',
            ScopeInterface::SCOPE_STORE,
            $store
        );

        if (empty($senderEmail)) {
            $senderEmail = (string) $this->scopeConfig->getValue(
                'trans_email/ident_sales/email',
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }

        // Use default if still empty
        return $senderEmail ?: 'noreply@buckaroo.nl';
    }
}
