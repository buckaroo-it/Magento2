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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Creditcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_creditcards';
    public const XPATH_CREDITCARDS_ALLOWED_ISSUERS = 'payment/buckaroo_magento2_creditcards/allowed_issuers';
    public const XPATH_CREDITCARDS_SORTED_ISSUERS = 'payment/buckaroo_magento2_creditcards/sorted_issuers';
    public const XPATH_USE_CARD_DESIGN             = 'card_design';
    public const XPATH_CREDITCARDS_PAYMENT_FEE = 'payment/buckaroo_magento2_creditcards/payment_fee';

    /**
     * Creditcard service codes.
     */
    public const CREDITCARD_SERVICE_CODE_MASTERCARD    = 'MasterCard';
    public const CREDITCARD_SERVICE_CODE_VISA          = 'Visa';
    public const CREDITCARD_SERVICE_CODE_AMEX          = 'Amex';
    public const CREDITCARD_SERVICE_CODE_MAESTRO       = 'Maestro';

    public const XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_ID = 'payment/buckaroo_magento2_creditcards/hosted_fields_client_id';
    public const XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_SECRET = 'payment/buckaroo_magento2_creditcards/hosted_fields_client_secret';

    public const XPATH_CREDITCARDS_PLACEHOLDER_CARDHOLDER_NAME = 'payment/buckaroo_magento2_creditcards/placeholder_cardholder_name';
    public const XPATH_CREDITCARDS_PLACEHOLDER_CARD_NUMBER = 'payment/buckaroo_magento2_creditcards/placeholder_card_number';
    public const XPATH_CREDITCARDS_PLACEHOLDER_EXPIRY_DATE = 'payment/buckaroo_magento2_creditcards/placeholder_expiry_date';
    public const XPATH_CREDITCARDS_PLACEHOLDER_CVC = 'payment/buckaroo_magento2_creditcards/placeholder_cvc';

    public const XPATH_CREDITCARDS_FIELD_TEXT_COLOR = 'payment/buckaroo_magento2_creditcards/field_text_color';
    public const XPATH_CREDITCARDS_FIELD_BACKGROUND_COLOR = 'payment/buckaroo_magento2_creditcards/field_background_color';
    public const XPATH_CREDITCARDS_FIELD_BORDER_COLOR = 'payment/buckaroo_magento2_creditcards/field_border_color';
    public const XPATH_CREDITCARDS_FIELD_PLACEHOLDER_COLOR = 'payment/buckaroo_magento2_creditcards/field_placeholder_color';
    public const XPATH_CREDITCARDS_FIELD_FONT_SIZE = 'payment/buckaroo_magento2_creditcards/field_font_size';
    public const XPATH_CREDITCARDS_FIELD_FONT_FAMILY = 'payment/buckaroo_magento2_creditcards/field_font_family';
    public const XPATH_CREDITCARDS_FIELD_BORDER_RADIUS = 'payment/buckaroo_magento2_creditcards/field_border_radius';


    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    protected array $issuers = [
        [
            'name' => 'American Express',
            'code' => self::CREDITCARD_SERVICE_CODE_AMEX,
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
            'name' => 'VISA',
            'code' => self::CREDITCARD_SERVICE_CODE_VISA,
            'sort' => 0
        ]
    ];

    /**
     * Creditcards constructor.
     *
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param LogoService $logoService
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);
        $this->storeManager = $storeManager;
    }

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

        return $this->fullConfig([
            'creditcards'       => $this->formatIssuers(),
            'defaultCardImage'  => $this->getDefaultCardImage(),
            'placeholders'      => [
                'cardholderName' => $this->getPlaceholderCardholderName(),
                'cardNumber'     => $this->getPlaceholderCardNumber(),
                'expiryDate'     => $this->getPlaceholderExpiryDate(),
                'cvc'            => $this->getPlaceholderCvc(),
            ],
            'styling'           => [
                'textColor'         => $this->getFieldTextColor(),
                'backgroundColor'   => $this->getFieldBackgroundColor(),
                'borderColor'       => $this->getFieldBorderColor(),
                'placeholderColor'  => $this->getFieldPlaceholderColor(),
                'fontSize'          => $this->getFieldFontSize(),
                'fontFamily'        => $this->getFieldFontFamily(),
                'borderRadius'      => $this->getFieldBorderRadius(),
            ],
        ]);
    }

    public function getIssuers(): array
    {
        return $this->issuers;
    }


    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @param null|int|string $storeId
     * @return array
     */
    public function formatIssuers($storeId = null): array
    {
        // If no store ID provided, use the current store
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $allowedConfig = (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Handle empty configuration by filtering out empty values
        $allowed = array_filter(explode(',', $allowedConfig));

        $sort = (string)$this->getSortedIssuers($storeId);
        $sortedArray = [];

        if (!empty($sort)) {
            $sorted = explode(',', $sort);
            $sortedPosition = 1;
            foreach ($sorted as $issuerCode) {
                $sortedArray[$issuerCode] = $sortedPosition++;
            }
        }

        $issuers = $this->issuers;
        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
            $issuers[$key]['img'] = $this->getCreditcardLogo($issuer['code']);
            $issuers[$key]['sort'] = $sortedArray[$issuer['code']] ?? '99';
        }

        // Sort by custom order if defined
        if (!empty($sortedArray)) {
            usort($issuers, function ($issuerA, $issuerB) {
                return $issuerA['sort'] - $issuerB['sort'];
            });
        }

        return $issuers;
    }

    /**
     * Get Active Status Cm3
     *
     * @return null
     */
    public function getActiveStatusCm3()
    {
        return null;
    }

    /**
     * Get Default Card Image
     *
     * @return string
     */
    public function getDefaultCardImage(): string
    {
        return $this->getImageUrl('svg/creditcards', 'svg');
    }

    /**
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }

    public function getHostedFieldsClientId()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_ID,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getHostedFieldsClientSecret()
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_HOSTED_FIELDS_CLIENT_SECRET,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get placeholder text for cardholder name field
     *
     * @param null|int $storeId
     * @return string
     */
    public function getPlaceholderCardholderName($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PLACEHOLDER_CARDHOLDER_NAME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'John Doe';
    }

    /**
     * Get placeholder text for card number field
     *
     * @param null|int $storeId
     * @return string
     */
    public function getPlaceholderCardNumber($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PLACEHOLDER_CARD_NUMBER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '555x xxxx xxxx xxxx';
    }

    /**
     * Get placeholder text for expiry date field
     *
     * @param null|int $storeId
     * @return string
     */
    public function getPlaceholderExpiryDate($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PLACEHOLDER_EXPIRY_DATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'MM / YY';
    }

    /**
     * Get placeholder text for CVC field
     *
     * @param null|int $storeId
     * @return string
     */
    public function getPlaceholderCvc($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_PLACEHOLDER_CVC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '1234';
    }

    /**
     * Get text color for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldTextColor($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_TEXT_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '#333333';
    }

    /**
     * Get background color for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldBackgroundColor($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_BACKGROUND_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '#fefefe';
    }

    /**
     * Get border color for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldBorderColor($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_BORDER_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '#d6d6d6';
    }

    /**
     * Get placeholder color for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldPlaceholderColor($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_PLACEHOLDER_COLOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '#888888';
    }

    /**
     * Get font size for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldFontSize($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_FONT_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '14px';
    }

    /**
     * Get font family for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldFontFamily($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_FONT_FAMILY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'Open Sans, Helvetica Neue, Helvetica, Arial, sans-serif';
    }

    /**
     * Get border radius for hosted fields
     *
     * @param null|int $storeId
     * @return string
     */
    public function getFieldBorderRadius($storeId = null): string
    {
        return $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_FIELD_BORDER_RADIUS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: '5px';
    }

    public function getSupportedServices(): array
    {
        $issuers = $this->formatIssuers();
        $supportedServices = [];

        foreach ($issuers as $issuer) {
            if ($issuer['active']) {
                $supportedServices[] = $issuer['code'];
            }
        }

        return $supportedServices;
    }

    /**
     * Get Sorted Issuers
     *
     * @param $store
     * @return mixed|null
     */
    public function getSortedIssuers($store = null)
    {
        $sorted = $this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_SORTED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        // Handle empty placeholder - return empty string instead of __EMPTY__
        if ($sorted === '__EMPTY__') {
            return '';
        }

        return $sorted;
    }

    /**
     * Get all available credit card issuers for the SortIssuers block
     * Only returns credit cards that are selected in "Allowed credit and debit cards"
     *
     * @param null|int|string $storeId
     * @return array
     */
    public function getAllIssuers($storeId = null): array
    {
        // If no store ID provided, use the current store
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }

        $allowedConfig = (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Handle empty configuration by filtering out empty values
        $allowed = array_filter(explode(',', $allowedConfig));

        // If no cards are selected, return empty array - merchant must configure cards
        if (empty($allowed)) {
            return [];
        }

        // Convert allowed codes to lowercase for case-insensitive comparison
        $allowedLowercase = array_map('strtolower', $allowed);

        $issuers = [];
        foreach ($this->issuers as $issuer) {
            // Compare with case-insensitive logic
            if (in_array(strtolower($issuer['code']), $allowedLowercase)) {
                $issuers[$issuer['code']] = [
                    'code' => $issuer['code'],
                    'name' => $issuer['name'],
                    'img' => $this->getCreditcardLogo($issuer['code'])
                ];
            }
        }

        return $issuers;
    }
}
