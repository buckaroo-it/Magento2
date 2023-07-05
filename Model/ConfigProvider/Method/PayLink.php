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

use Buckaroo\Magento2\Exception;
use Magento\Store\Model\ScopeInterface;

class PayLink extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paylink';

    public const XPATH_PAYLINK_SUBTEXT        = 'payment/buckaroo_magento2_paylink/subtext';
    public const XPATH_PAYLINK_SUBTEXT_STYLE  = 'payment/buckaroo_magento2_paylink/subtext_style';
    public const XPATH_PAYLINK_SUBTEXT_COLOR  = 'payment/buckaroo_magento2_paylink/subtext_color';
    public const XPATH_PAYLINK_PAYMENT_METHOD = 'payment/buckaroo_magento2_paylink/payment_method';

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'paylink'  => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'   => $this->getSubtext(),
                        'subtext_style'   => $this->getSubtextStyle(),
                        'subtext_color'   => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                    'response' => [],
                ],
            ],
        ];
    }

    /**
     * Payment method is visible for area code
     *
     * @param string $areaCode
     * @return bool
     */
    public function isVisibleForAreaCode(string $areaCode): bool
    {
        if ($areaCode == 'adminhtml') {
            return true;
        }

        return false;
    }

    /**
     * Get payment method from paylink paymennt methods list
     *
     * @param $store
     * @return mixed
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
     * Can send mail by email
     *
     * @return bool
     */
    public function hasSendMail(): bool
    {
        $sendMail = $this->scopeConfig->getValue(
            PayPerEmail::XPATH_PAYPEREMAIL_SEND_MAIL,
            ScopeInterface::SCOPE_STORE
        );

        return (bool)$sendMail;
    }
}
