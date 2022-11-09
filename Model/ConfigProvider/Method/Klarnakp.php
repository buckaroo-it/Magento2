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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Magento\Store\Model\ScopeInterface;

class Klarnakp extends AbstractConfigProvider
{
    const CODE = 'buckaroo_magento2_klarnakp';

    const XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP = 'payment/buckaroo_magento2_klarnakp/create_invoice_after_shipment';

    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Klarnakp::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'klarnakp' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'paymentFee'        => $this->getPaymentFee(),
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * Get Create Invoice After Shipment
     *
     * @param null|int|string $store
     * @return bool
     */
    public function getCreateInvoiceAfterShipment($store = null): bool
    {
        $createInvoiceAfterShipment = (bool)$this->scopeConfig->getValue(
            static::XPATH_KLARNAKP_CREATE_INVOICE_BY_SHIP,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $createInvoiceAfterShipment ?: false;
    }
}
