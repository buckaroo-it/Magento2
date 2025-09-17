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

use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Gateway\Request\SaveIssuerDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class PayByBank extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_paybybank';

    public const XPATH_ACCOUNT_SELECTION_TYPE = 'buckaroo_magento2/account/selection_type';
    public const XPATH_SORTED_ISSUERS           = 'payment/buckaroo_magento2_paybybank/sorted_issuers';
    public const XPATH_ALLOWED_ISSUERS          = 'payment/buckaroo_magento2_paybybank/allowed_issuers';


    protected array $issuers = [
        [
            'name'    => 'ABN AMRO',
            'code'    => 'ABNANL2A',
            'imgName' => 'abnamro'
        ],
        [
            'name'    => 'ASN Bank',
            'code'    => 'ASNBNL21',
            'imgName' => 'asnbank'
        ],
        [
            'name'    => 'ING',
            'code'    => 'INGBNL2A',
            'imgName' => 'ing'
        ],
        [
            'name'    => 'Knab Bank',
            'code'    => 'KNABNL2H',
            'imgName' => 'knab'
        ],
        [
            'name'    => 'Rabobank',
            'code'    => 'RABONL2U',
            'imgName' => 'rabobank'
        ],
        [
            'name'    => 'RegioBank',
            'code'    => 'RBRBNL21',
            'imgName' => 'regiobank'
        ],
        [
            'name'    => 'SNS Bank',
            'code'    => 'SNSBNL2A',
            'imgName' => 'sns'
        ],
        [
            'name'    => 'N26',
            'code'    => 'NTSBDEB1',
            'imgName' => 'n26'
        ]
    ];

    /**
     * @var CustomerSession
     */
    protected CustomerSession $customerSession;
    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param LogoService $logoService
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        CustomerSession $customerSession
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);
        $this->customerSession = $customerSession;
    }

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

        return $this->fullConfig([
            'banks'             => $this->getIssuersWithSelected(),
            'selectionType'     => $selectionType,
        ]);
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

    /**
     * @param $storeId
     * @return mixed
     */
    public function getSortedIssuers($storeId = null)
    {
        $sortedIssuers = $this->scopeConfig->getValue(
            self::XPATH_SORTED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';

        // Convert __EMPTY__ placeholder back to empty string
        if ($sortedIssuers === '__EMPTY__') {
            return '';
        }

        return $sortedIssuers;
    }

    /**
     * Get all available PayByBank issuers filtered by allowed selection
     *
     * @return array
     */
    public function getAllIssuers(): array
    {
        $allowedCodes = $this->getAllowedIssuers();

        if (empty($allowedCodes)) {
            return [];
        }

        $allowedCodesArray = explode(',', $allowedCodes);
        $allIssuers = [];

        foreach ($this->issuers as $issuer) {
            if (in_array($issuer['code'], $allowedCodesArray)) {
                $allIssuers[$issuer['code']] = [
                    'name' => $issuer['name'],
                    'code' => $issuer['code'],
                    'img' => $this->getImageUrl($issuer['imgName'])
                ];
            }
        }

        return $allIssuers;
    }

    /**
     * Get allowed PayByBank issuers configuration
     *
     * @param $storeId
     * @return string
     */
    public function getAllowedIssuers($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_ALLOWED_ISSUERS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?? '';
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
