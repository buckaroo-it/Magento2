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

namespace TIG\Buckaroo\Model\ConfigProvider\Method;

/**
 * @method getPaymentFeeLabel();
 * @method getMaestroUnsecureHold()
 * @method getMastercardUnsecureHold()
 * @method getVisaUnsecureHold()
 * @method getAllowedCreditcards()
 */
class Creditcard extends AbstractConfigProvider
{
    /**#@+
     * Creditcard service codes.
     */
    const CREDITCARD_SERVICE_CODE_MASTERCARD    = 'mastercard';
    const CREDITCARD_SERVICE_CODE_VISA          = 'visa';
    const CREDITCARD_SERVICE_CODE_AMEX          = 'amex';
    const CREDITCARD_SERVICE_CODE_MAESTRO       = 'maestro';
    const CREDITCARD_SERVICE_CODE_VPAY          = 'vpay';
    const CREDITCARD_SERVICE_CODE_VISAELECTRON  = 'visaelectron';
    const CREDITCARD_SERVICE_CODE_CARTEBLEUE    = 'cartebleuevisa';
    const CREDITCARD_SERVICE_CODE_CARTEBANCAIRE = 'cartebancaire';
    const CREDITCARD_SERVICE_CODE_DANKORT       = 'dankort';
    const CREDITCARD_SERVICE_CODE_NEXI          = 'nexi';
    /**#@-*/

    const XPATH_CREDITCARD_PAYMENT_FEE          = 'payment/tig_buckaroo_creditcard/payment_fee';
    const XPATH_CREDITCARD_PAYMENT_FEE_LABEL    = 'payment/tig_buckaroo_creditcard/payment_fee_label';
    const XPATH_CREDITCARD_ACTIVE               = 'payment/tig_buckaroo_creditcard/active';
    const XPATH_CREDITCARD_ACTIVE_STATUS        = 'payment/tig_buckaroo_creditcard/active_status';
    const XPATH_CREDITCARD_ORDER_STATUS_SUCCESS = 'payment/tig_buckaroo_creditcard/order_status_success';
    const XPATH_CREDITCARD_ORDER_STATUS_FAILED  = 'payment/tig_buckaroo_creditcard/order_status_failed';
    const XPATH_CREDITCARD_ALLOWED_CREDITCARDS  = 'payment/tig_buckaroo_creditcard/allowed_creditcards';

    const XPATH_CREDITCARD_MASTERCARD_UNSECURE_HOLD = 'payment/tig_buckaroo_creditcard/mastercard_unsecure_hold';
    const XPATH_CREDITCARD_VISA_UNSECURE_HOLD       = 'payment/tig_buckaroo_creditcard/visa_unsecure_hold';
    const XPATH_CREDITCARD_MAESTRO_UNSECURE_HOLD    = 'payment/tig_buckaroo_creditcard/maestro_unsecure_hold';

    const XPATH_ALLOWED_CURRENCIES = 'payment/tig_buckaroo_creditcard/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/tig_buckaroo_creditcard/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/tig_buckaroo_creditcard/specificcountry';

    protected $issuers = [
        [
            'name' => 'American Express',
            'code' => self::CREDITCARD_SERVICE_CODE_AMEX,
        ],
        [
            'name' => 'Carte Bancaire',
            'code' => self::CREDITCARD_SERVICE_CODE_CARTEBANCAIRE,
        ],
        [
            'name' => 'Carte Bleue',
            'code' => self::CREDITCARD_SERVICE_CODE_CARTEBLEUE,
        ],
        [
            'name' => 'Dankort',
            'code' => self::CREDITCARD_SERVICE_CODE_DANKORT,
        ],
        [
            'name' => 'Maestro',
            'code' => self::CREDITCARD_SERVICE_CODE_MAESTRO,
        ],
        [
            'name' => 'MasterCard',
            'code' => self::CREDITCARD_SERVICE_CODE_MASTERCARD,
        ],
        [
            'name' => 'Nexi',
            'code' => self::CREDITCARD_SERVICE_CODE_NEXI,
        ],
        [
            'name' => 'VISA',
            'code' => self::CREDITCARD_SERVICE_CODE_VISA,
        ],
        [
            'name' => 'VISA Electron',
            'code' => self::CREDITCARD_SERVICE_CODE_VISAELECTRON,
        ],
        [
            'name' => 'VPay',
            'code' => self::CREDITCARD_SERVICE_CODE_VPAY,
        ],
    ];

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $issuers = parent::formatIssuers();
        $allowed = explode(',', $this->scopeConfig->getValue(
            self::XPATH_CREDITCARD_ALLOWED_CREDITCARDS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        );

        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
        }

        return $issuers;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this
            ->getBuckarooPaymentFeeLabel(\TIG\Buckaroo\Model\Method\Creditcard::PAYMENT_METHOD_CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcard' => [
                        'cards' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_CREDITCARD_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    /**
     * @param string $cardType
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getCardName($cardType)
    {
        $config = $this->getConfig();

        foreach ($config['payment']['buckaroo']['creditcard']['cards'] as $card) {
            if ($card['code'] == $cardType) {
                return $card['name'];
            }
        }

        throw new \InvalidArgumentException("No card found for card type: {$cardType}");
    }

    /**
     * @param string $cardType
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getCardCode($cardType)
    {
        $config = $this->getConfig();
        foreach ($config['payment']['buckaroo']['creditcard']['cards'] as $card) {
            if ($card['name'] == $cardType) {
                return $card['code'];
            }
        }

        throw new \InvalidArgumentException("No card found for card type: {$cardType}");
    }
}
