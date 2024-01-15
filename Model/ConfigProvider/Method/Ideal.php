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
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\Ideal\IssuersService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;

class Ideal extends AbstractConfigProvider
{
    /**
     * @var IssuersService
     */
    protected IssuersService $issuersService;

    public const CODE = 'buckaroo_magento2_ideal';

    public const XPATH_SELECTION_TYPE   = 'selection_type';
    public const XPATH_SHOW_ISSUERS     = 'show_issuers';
    public const XPATH_GATEWAY_SETTINGS = 'gateway_settings';
    public const XPATH_SORTED_ISSUERS   = 'sorted_issuers';

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
     * @param IssuersService $issuersService
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        IssuersService $issuersService
    ) {
        $this->issuersService = $issuersService;

        parent::__construct(
            $assetRepo,
            $scopeConfig,
            $allowedCurrencies,
            $paymentFeeHelper
        );
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

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        $selectionType = $this->getSelectionType();

        return [
            'payment' => [
                'buckaroo' => [
                    'ideal' => [
                        'banks'             => $issuers,
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType'     => $selectionType,
                        'showIssuers'       => $this->canShowIssuers(),
                        'isTestMode'        => $this->isTestMode()
                    ],
                ],
            ],
        ];
    }

    /**
     * Retrieve the list of issuers.
     *
     * @return array
     */
    public function getIssuers()
    {
        return $this->issuersService->get();
    }

    /**
     * Can show issuer selection in checkout
     *
     * @param string|null $storeId
     *
     * @return boolean
     */
    public function canShowIssuers(string $storeId = null): bool
    {
        return $this->getMethodConfigValue(self::XPATH_SHOW_ISSUERS, $storeId) == 1;
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
     * Get gateway setting ideal/idealprocessing
     *
     * @param null|int|string $storeId
     * @return string
     */
    public function getGatewaySettings($storeId = null): string
    {
        return $this->getMethodConfigValue(self::XPATH_GATEWAY_SETTINGS, $storeId) ??
            str_replace('buckaroo_magento2_', '', self::CODE);
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getSortedIssuers($storeId = null): string
    {
        return $this->getMethodConfigValue(self::XPATH_SORTED_ISSUERS, $storeId) ?? '';
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
