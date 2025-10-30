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

use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Model\Method\PayByBank as PayByBankMethod;

class PayByBank extends AbstractConfigProvider
{
    public const XPATH_PAYBYBANK_ACTIVE               = 'payment/buckaroo_magento2_paybybank/active';
    public const XPATH_PAYBYBANK_SUBTEXT              = 'payment/buckaroo_magento2_paybybank/subtext';
    public const XPATH_PAYBYBANK_SUBTEXT_STYLE        = 'payment/buckaroo_magento2_paybybank/subtext_style';
    public const XPATH_PAYBYBANK_SUBTEXT_COLOR        = 'payment/buckaroo_magento2_paybybank/subtext_color';
    public const XPATH_PAYBYBANK_ACTIVE_STATUS        = 'payment/buckaroo_magento2_paybybank/active_status';
    public const XPATH_PAYBYBANK_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_paybybank/order_status_success';
    public const XPATH_PAYBYBANK_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_paybybank/order_status_failed';
    public const XPATH_PAYBYBANK_ORDER_EMAIL          = 'payment/buckaroo_magento2_paybybank/order_email';
    public const XPATH_PAYBYBANK_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_paybybank/available_in_backend';

    public const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_paybybank/allowed_currencies';

    public const XPATH_ALLOW_SPECIFIC           = 'payment/buckaroo_magento2_paybybank/allowspecific';
    public const XPATH_SPECIFIC_COUNTRY         = 'payment/buckaroo_magento2_paybybank/specificcountry';
    public const XPATH_PAYBYBANK_SELECTION_TYPE = 'buckaroo_magento2/account/selection_type';
    public const XPATH_SPECIFIC_CUSTOMER_GROUP  = 'payment/buckaroo_magento2_paybybank/specificcustomergroup';
    public const XPATH_SORTED_ISSUERS           = 'payment/buckaroo_magento2_paybybank/sorted_issuers';


    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
            'imgName' => 'abnamro',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
            'imgName' => 'asnbank',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
            'imgName' => 'ing',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
            'imgName' => 'knab',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
            'imgName' => 'rabobank',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
            'imgName' => 'regiobank',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
            'imgName' => 'sns',
        ],
        [
            'name' => 'N26',
            'code' => 'NTSBDEB1',
            'imgName' => 'n26',
        ],
    ];

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param CustomerSession      $customerSession
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        CustomerSession $customerSession
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);
        $this->customerSession = $customerSession;
    }

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
    ];

    /**
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->scopeConfig->getValue(
            static::XPATH_PAYBYBANK_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }


        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_PAYBYBANK_SELECTION_TYPE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'paybybank' => [
                        'banks'             => $this->getIssuersWithSelected(),
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType'     => $selectionType,
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
        return 0;
    }

    /**
     * Get bank issuers, move last saved bank issuer first,
     * add selected property to the issuers array
     *
     * @return array
     */
    public function getIssuersWithSelected()
    {
        $issuers = $this->formatIssuers();
        $customer = $this->customerSession->getCustomer();
        $savedBankIssuer = $customer->getData(PayByBankMethod::EAV_LAST_USED_ISSUER_ID);

        if ($savedBankIssuer !== null) {
            $issuers = array_map(function ($issuer) use ($savedBankIssuer) {
                $issuer['selected'] = is_scalar($savedBankIssuer) && isset($issuer['code']) && $issuer['code'] === $savedBankIssuer;
                return $issuer;
            }, $issuers);


            $savedIssuer = array_filter($issuers, function ($issuer) {
                return $issuer['selected'];
            });
            $issuers = array_filter($issuers, function ($issuer) {
                return !$issuer['selected'];
            });

            return array_merge($savedIssuer, $issuers);
        }

        return $issuers;
    }

    /**
     * @param        $storeId
     * @return mixed
     */
    public function getSortedIssuers($storeId = null)
    {
        $sorted = $this->scopeConfig->getValue(
            self::XPATH_SORTED_ISSUERS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($sorted && trim($sorted) !== '') {
            return $sorted;
        }
        // Fallback: return all default issuer codes, comma-separated
        $codes = array_column($this->issuers, 'code');
        return implode(',', $codes);
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
        return parent::getImageUrl("ideal/{$imgName}", "svg");
    }
}
