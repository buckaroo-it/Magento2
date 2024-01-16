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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;

class Afterpay20 extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_afterpay20';

    public const XPATH_AFTERPAY20_CREATE_INVOICE_BY_SHIP = 'create_invoice_after_shipment';

    public const XPATH_AFTERPAY20_CUSTOMER_TYPE  = 'customer_type';
    public const XPATH_AFTERPAY20_MIN_AMOUNT_B2B = 'min_amount_b2b';
    public const XPATH_AFTERPAY20_MAX_AMOUNT_B2B = 'max_amount_b2b';

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'afterpay20' => [
                        'sendEmail'            => $this->hasOrderEmail(),
                        'paymentFeeLabel'      => $paymentFeeLabel,
                        'subtext'              => $this->getSubtext(),
                        'subtext_style'        => $this->getSubtextStyle(),
                        'subtext_color'        => $this->getSubtextColor(),
                        'allowedCurrencies'    => $this->getAllowedCurrencies(),
                        'is_b2b'               => $this->getCustomerType() !== AfterpayCustomerType::CUSTOMER_TYPE_B2C,
                        'showFinancialWarning' => $this->canShowFinancialWarning(),
                        'isTestMode'           => $this->isTestMode()
                    ],
                    'response'   => [],
                ],
            ],
        ];
    }

    /**
     * Get customer type
     *
     * @param null|int $storeId
     * @return string
     */
    public function getCustomerType($storeId = null)
    {
        return $this->getMethodConfigValue(self::XPATH_AFTERPAY20_CUSTOMER_TYPE, $storeId);
    }

    /**
     * Create invoice after shipment
     *
     * @param null|int|string $storeId
     * @return bool
     */
    public function isInvoiceCreatedAfterShipment($storeId = null): bool
    {
        $createInvoiceAfterShipment = $this->getMethodConfigValue(self::XPATH_AFTERPAY20_CUSTOMER_TYPE, $storeId);

        return $createInvoiceAfterShipment ?: false;
    }
}
