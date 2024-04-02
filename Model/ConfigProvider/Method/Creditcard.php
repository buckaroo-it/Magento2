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

class Creditcard extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_creditcard';

    /**
     * Creditcard service codes.
     */
    private const CREDITCARD_SERVICE_CODE_MASTERCARD    = 'mastercard';
    private const CREDITCARD_SERVICE_CODE_VISA          = 'visa';
    private const CREDITCARD_SERVICE_CODE_AMEX          = 'amex';
    private const CREDITCARD_SERVICE_CODE_MAESTRO       = 'maestro';
    private const CREDITCARD_SERVICE_CODE_VPAY          = 'vpay';
    private const CREDITCARD_SERVICE_CODE_VISAELECTRON  = 'visaelectron';
    private const CREDITCARD_SERVICE_CODE_CARTEBLEUE    = 'cartebleuevisa';
    private const CREDITCARD_SERVICE_CODE_CARTEBANCAIRE = 'cartebancaire';
    private const CREDITCARD_SERVICE_CODE_DANKORT       = 'dankort';
    private const CREDITCARD_SERVICE_CODE_NEXI          = 'nexi';
    private const CREDITCARD_SERVICE_CODE_POSTEPAY      = 'postepay';

    public const XPATH_CREDITCARD_MASTERCARD_UNSECURE_HOLD = 'mastercard_unsecure_hold';
    public const XPATH_CREDITCARD_VISA_UNSECURE_HOLD       = 'visa_unsecure_hold';
    public const XPATH_CREDITCARD_MAESTRO_UNSECURE_HOLD    = 'maestro_unsecure_hold';

    public const XPATH_CREDITCARD_ALLOWED_CREDITCARDS = 'allowed_issuers';
    public const XPATH_SORTED_ISSUERS                 = 'sorted_issuers';
    public const XPATH_CREDITCARD_GROUP_CREDITCARD    = 'group_creditcards';
    public const XPATH_SELECTION_TYPE                 = 'selection_type';
    public const XPATH_PAYMENT_FLOW                   = 'payment_action';
    public const DEFAULT_SORT_VALUE                   = '99';

    /** @var array[] */
    protected array $issuers = [
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
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'cards'             => $this->formatIssuers(),
            'groupCreditcards'  => $this->isGroupCreditcards(),
            'selectionType'     => $this->getSelectionType(),
            'paymentFlow'       => $this->getPaymentFlow(),
        ]);
    }

    public function getIssuers(): array
    {
        return $this->issuers;
    }

    /**
     * Get all issuers not sorted
     *
     * @return array
     */
    public function getAllIssuers(): array
    {
        $issuers = $this->getIssuers();
        $issuersPrepared = [];
        foreach ($issuers as $issuer) {
            $issuer['img'] = $this->getImageUrl($issuer['code']);
            $issuersPrepared[$issuer['code']] = $issuer;
        }

        return $issuersPrepared;
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers(): array
    {
        $sorted = $this->getSortedIssuers();
        $sorted = $sorted ? explode(',', $sorted) : [];

        if (!empty($sorted)) {
            $sortedPosition = 1;
            foreach ($sorted as $cardCode) {
                $sorted_array[$cardCode] = $sortedPosition++;
            }
        }

        foreach ($this->getIssuers() as $item) {
            $item['sort'] = isset($sorted_array[$item['code']]) ?
                $sorted_array[$item['code']] : self::DEFAULT_SORT_VALUE;
            $item['img'] = $this->getImageUrl($item['code']);
            $allCreditcard[$item['code']] = $item;
        }

        $allowed = explode(',', (string)$this->getAllowedCreditcards());
        $cards = [];
        foreach ($allowed as $value) {
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
     * Generate the url to the desired asset.
     *
     * @param string $imgName
     * @param string $extension
     *
     * @return string
     */
    public function getImageUrl($imgName, string $extension = 'png')
    {
        if ($imgName === 'cartebleuevisa') {
            $imgName = 'cartebleue';
        }

        return parent::getImageUrl("creditcards/{$imgName}", "svg");
    }

    /**
     * Get card name by card type
     *
     * @param string $cardType
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
     * Get card code by card type
     *
     * @param string $cardType
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

    /**
     * Retrieve the sorted order of the credit card types to display on the checkout page
     *
     * @param $storeId
     * @return ?string
     */
    public function getSortedIssuers($storeId = null): ?string
    {
        return $this->getMethodConfigValue(self::XPATH_SORTED_ISSUERS, $storeId);
    }

    /**
     * Get the list with allowed credit cards
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getAllowedCreditcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CREDITCARD_ALLOWED_CREDITCARDS, $store);
    }

    /**
     * Selection type radio checkbox or drop down
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getSelectionType($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_SELECTION_TYPE, $store);
    }

    /**
     * Get Payment Flow - Order vs Authorize/Capture
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getPaymentFlow($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_PAYMENT_FLOW, $store);
    }

    /**
     * Credit cards are displayed separately in the checkout.
     *
     * @param $storeId
     * @return string
     */
    public function isGroupCreditcards($storeId = null): string
    {
        return (bool)$this->getMethodConfigValue(self::XPATH_CREDITCARD_GROUP_CREDITCARD, $storeId);
    }

    /**
     * Hold orders which have no MasterCard SecureCode.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMastercardUnsecureHold($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CREDITCARD_MASTERCARD_UNSECURE_HOLD, $store);
    }

    /**
     * Hold orders which have no Visa SecureCode.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getVisaUnsecureHold($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CREDITCARD_VISA_UNSECURE_HOLD, $store);
    }

    /**
     * Hold orders which have no Maestro SecureCode.
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getMaestroUnsecureHold($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CREDITCARD_MAESTRO_UNSECURE_HOLD, $store);
    }
}
