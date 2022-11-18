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

class PayLink extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paylink';

    public const XPATH_PAYLINK_PAYMENT_METHOD = 'payment/buckaroo_magento2_paylink/payment_method';

    /**
     * @inheritDoc
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
                    'paylink' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * @param $areaCode
     * @return bool
     */
    public function isVisibleForAreaCode($areaCode)
    {
        if ($areaCode == 'adminhtml') {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethod($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_PAYLINK_PAYMENT_METHOD,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @return bool
     */
    public function getSendMail()
    {
        $sendMail = $this->scopeConfig->getValue(
            PayPerEmail::XPATH_PAYPEREMAIL_SEND_MAIL,
            ScopeInterface::SCOPE_STORE
        );

        return (bool)$sendMail;
    }
}
