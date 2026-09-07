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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

/**
 * Configuration a payment method must expose to take part in Credit Management (CM3).
 *
 * Only PayPerEmail, Bank Transfer and SEPA Direct Debit offer the "Credit Management
 * Enabled" setting, and the CM3 request builders read exactly these values. Declaring
 * them here lets those builders depend on the capability rather than on
 * AbstractConfigProvider, which defines none of them.
 */
interface Cm3ConfigProviderInterface
{
    /**
     * Whether Credit Management is enabled for this payment method.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getActiveStatusCm3($store = null);

    /**
     * Credit Management scheme key, from Plaza > Invoices > Scheme.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getSchemeKey($store = null);

    /**
     * Highest Credit Management step to run.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getMaxStepIndex($store = null);

    /**
     * Days after the order date on which the invoice falls due.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getCm3DueDate($store = null);

    /**
     * Payment methods offered after the due date has passed.
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getPaymentMethodAfterExpiry($store = null);

    /**
     * Payment methods offered on the invoice, or false when unrestricted.
     *
     * @param int|null $storeId
     *
     * @return false|mixed
     */
    public function getPaymentMethod(?int $storeId = null);
}
