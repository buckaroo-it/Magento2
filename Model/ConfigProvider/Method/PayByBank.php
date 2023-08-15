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

use Buckaroo\Magento2\Gateway\Request\SaveIssuerDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\ScopeInterface;

class PayByBank extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paybybank';

    public const XPATH_ACCOUNT_SELECTION_TYPE = 'buckaroo_magento2/account/selection_type';

    protected array $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
            'imgName' => 'abnamro'
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
            'imgName' => 'asnbank'
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
            'imgName' => 'ing'
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
            'imgName' => 'knab'
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
            'imgName' => 'rabobank'
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
            'imgName' => 'regiobank'
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
            'imgName' => 'sns'
        ],
        [
            'name' => 'N26',
            'code' => 'NTSBDEB1',
            'imgName' => 'n26'
        ]
    ];

    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param CustomerSession $customerSession
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
        'EUR'
    ];

    /**
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $selectionType = $this->scopeConfig->getValue(
            self::XPATH_ACCOUNT_SELECTION_TYPE,
            ScopeInterface::SCOPE_STORE
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
     * @inheritdoc
     */
    public function getPaymentFee($store = null)
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFeeLabel($store = null)
    {
        return  $this->scopeConfig->getValue(
            Account::XPATH_ACCOUNT_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get bank issuers, move last saved bank issuer first,
     * add selected property to the issuers array
     *
     * @return array
     */
    public function getIssuersWithSelected(): array
    {
        $issuers = $this->formatIssuers();
        $customer = $this->customerSession->getCustomer();
        $savedBankIssuer = $customer->getData(SaveIssuerDataBuilder::EAV_LAST_USED_ISSUER_ID);

        if ($savedBankIssuer !== null) {
            $issuers = array_map(function ($issuer) use ($savedBankIssuer) {
                $issuer['selected'] = is_scalar($savedBankIssuer)
                    && isset($issuer['code'])
                    && $issuer['code'] === $savedBankIssuer;
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
}
