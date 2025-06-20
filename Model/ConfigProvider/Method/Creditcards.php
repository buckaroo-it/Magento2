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
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);
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
        ]);
    }

    public function getIssuers(): array
    {
        return $this->issuers;
    }


    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers(): array
    {
        $allowed = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE
        ));

        $sort = (string)$this->getSortedIssuers();
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
     * @return array
     */
    public function getAllIssuers(): array
    {
        // Get only allowed credit cards
        $allowed = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_CREDITCARDS_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE
        ));

        if (count($allowed) === 1 && empty($allowed[0])) {
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
