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
    const CREDITCARD_SERVICE_CODE_POSTEPAY      = 'postepay';
    /**#@-*/

    const XPATH_CREDITCARD_PAYMENT_FEE          = 'payment/buckaroo_magento2_creditcard/payment_fee';
    const XPATH_CREDITCARD_PAYMENT_FEE_LABEL    = 'payment/buckaroo_magento2_creditcard/payment_fee_label';
    const XPATH_CREDITCARD_ACTIVE               = 'payment/buckaroo_magento2_creditcard/active';
    const XPATH_CREDITCARD_ACTIVE_STATUS        = 'payment/buckaroo_magento2_creditcard/active_status';
    const XPATH_CREDITCARD_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_creditcard/order_status_success';
    const XPATH_CREDITCARD_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_creditcard/order_status_failed';
    const XPATH_CREDITCARD_ALLOWED_CREDITCARDS  = 'payment/buckaroo_magento2_creditcard/allowed_creditcards';

    const XPATH_CREDITCARD_MASTERCARD_UNSECURE_HOLD = 'payment/buckaroo_magento2_creditcard/mastercard_unsecure_hold';
    const XPATH_CREDITCARD_VISA_UNSECURE_HOLD       = 'payment/buckaroo_magento2_creditcard/visa_unsecure_hold';
    const XPATH_CREDITCARD_MAESTRO_UNSECURE_HOLD    = 'payment/buckaroo_magento2_creditcard/maestro_unsecure_hold';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_creditcard/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC                  = 'payment/buckaroo_magento2_creditcard/allowspecific';
    const XPATH_SPECIFIC_COUNTRY                = 'payment/buckaroo_magento2_creditcard/specificcountry';
    const XPATH_CREDITCARD_SORT                 = 'payment/buckaroo_magento2_creditcard/sorted_creditcards';
    const XPATH_SELECTION_TYPE                  = 'buckaroo_magento2/account/selection_type';
    const XPATH_PAYMENT_FLOW                    = 'payment/buckaroo_magento2_creditcard/payment_action';
    const DEFAULT_SORT_VALUE                    = '99';

    const XPATH_SPECIFIC_CUSTOMER_GROUP            = 'payment/buckaroo_magento2_creditcard/specificcustomergroup';

    protected $issuers = [
        [
            'name' => 'American Express',
            'code' => self::CREDITCARD_SERVICE_CODE_AMEX,
            'sort' => 0
        ],
        [
            'name' => 'Carte Bancaire',
            'code' => self::CREDITCARD_SERVICE_CODE_CARTEBANCAIRE,
            'sort' => 0
        ],
        [
            'name' => 'Carte Bleue',
            'code' => self::CREDITCARD_SERVICE_CODE_CARTEBLEUE,
            'sort' => 0
        ],
        [
            'name' => 'Dankort',
            'code' => self::CREDITCARD_SERVICE_CODE_DANKORT,
            'sort' => 0
        ],
        [
            'name' => 'Maestro',
            'code' => self::CREDITCARD_SERVICE_CODE_MAESTRO,
            'sort' => 0
        ],
        [
            'name' => 'MasterCard',
            'code' => self::CREDITCARD_SERVICE_CODE_MASTERCARD,
            'sort' => 0
        ],
        [
            'name' => 'Nexi',
            'code' => self::CREDITCARD_SERVICE_CODE_NEXI,
            'sort' => 0
        ],
        [
            'name' => 'PostePay',
            'code' => self::CREDITCARD_SERVICE_CODE_POSTEPAY,
            'sort' => 0
        ],
        [
            'name' => 'VISA',
            'code' => self::CREDITCARD_SERVICE_CODE_VISA,
            'sort' => 0
        ],
        [
            'name' => 'VISA Electron',
            'code' => self::CREDITCARD_SERVICE_CODE_VISAELECTRON,
            'sort' => 0
        ],
        [
            'name' => 'VPay',
            'code' => self::CREDITCARD_SERVICE_CODE_VPAY,
            'sort' => 0
        ],
    ];

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers()
    {
        $sorted = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARD_SORT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));

        if (!empty($sorted)) {
            $sortedPosition = 1;
            foreach ($sorted as $cardName) {
                $sorted_array[$cardName] = $sortedPosition++;
            }
        }

        $issuers = parent::formatIssuers();
        foreach ($issuers as $item) {
            $item['sort'] = isset($sorted_array[$item['name']]) ?
                $sorted_array[$item['name']] : self::DEFAULT_SORT_VALUE;
            $allCreditcard[$item['code']] = $item;
        }

        $allowed = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARD_ALLOWED_CREDITCARDS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));

        $cards = [];
        foreach ($allowed as $key => $value) {
            if (isset($allCreditcard[$value])) {
                $cards[] = $allCreditcard[$value];
            }
        }

        usort($cards, function ($cardA, $cardB) {
            return $cardA['sort'] - $cardB['sort'];
        });

        return $cards;
    }

    /**
     * @return array|void
     */
    public function getConfig()
    {
        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Creditcard::PAYMENT_METHOD_CODE
        );

        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $paymentFlow = $this->scopeConfig->getValue(
            self::XPATH_PAYMENT_FLOW,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcard' => [
                        'cards' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                        'paymentFlow' => $paymentFlow,
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
