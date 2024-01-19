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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\StoreManagerInterface;

class Giftcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_giftcards';

    public const XPATH_GIFTCARDS_ALLOWED_GIFTCARDS       = 'allowed_giftcards';
    public const XPATH_GIFTCARDS_GROUP_GIFTCARDS         = 'group_giftcards';
    public const XPATH_GIFTCARDS_SORT                    = 'sorted_giftcards';
    public const XPATH_ACCOUNT_ADVANCED_EXPORT_GIFTCARDS = 'buckaroo_magento2/account/advanced_export_giftcards';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
    ];

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @throws Exception|NoSuchEntityException
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $sort = (string)$this->getSort();

        if (!empty($sort)) {
            $sorted = explode(',', (string)$this->getSort());
            $sortedPosition = 1;
            foreach ($sorted as $cardName) {
                $sortedArray[$cardName] = $sortedPosition++;
            }
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('buckaroo_magento2_giftcard');
        $result = $connection->fetchAll("SELECT * FROM " . $tableName);
        foreach ($result as $item) {
            $item['sort'] = $sortedArray[$item['label']] ?? '99';
            $allGiftCards[$item['servicecode']] = $item;
        }

        $availableCards = $this->getAllowedGiftcards();

        $url = $this->storeManager->getStore()->getBaseUrl(
            UrlInterface::URL_TYPE_MEDIA
        );

        foreach (explode(',', (string)$availableCards) as $value) {
            $logo = $this->getLogo($value);
            if (isset($allGiftCards[$value]['logo'])) {
                $logo = $url . $allGiftCards[$value]['logo'];
            }

            $cards[] = [
                'code'  => $value,
                'title' => $allGiftCards[$value]['label'] ?? '',
                'logo'  => $logo,
                'sort'  => $allGiftCards[$value]['sort'] ?? '99',
            ];
        }

        usort($cards, function ($cardA, $cardB) {
            return $cardA['sort'] - $cardB['sort'];
        });

        return [
            'payment' => [
                'buckaroo' => [
                    'groupGiftcards'   => $this->getGroupGiftcards(),
                    'avaibleGiftcards' => $cards,
                    'giftcards'        => [
                        'paymentFeeLabel'   => $this->getBuckarooPaymentFeeLabel(),
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'isTestMode'        => $this->isTestMode()
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Giftcards Sort Order
     *
     * @param $store
     * @return mixed|null
     */
    public function getSort($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_SORT, $store);
    }

    /**
     * Get Allowed Giftcards
     *
     * @param $store
     * @return mixed|null
     */
    public function getAllowedGiftcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS, $store);
    }

    /**
     * Get giftcard logo image
     *
     * @param string $code
     * @return string
     */
    protected function getLogo(string $code): string
    {
        $mappings = [
            "ajaxgiftcard"               => "ajaxgiftcard",
            "boekenbon"                  => "boekenbon",
            "cjpbetalen"                 => "cjp",
            "digitalebioscoopbon"        => "nationaletuinbon",
            "fashioncheque"              => "fashioncheque",
            "fashionucadeaukaart"        => "fashiongiftcard",
            "nationaletuinbon"           => "nationalebioscoopbon",
            "nationaleentertainmentcard" => "nationaleentertainmentcard",
            "podiumcadeaukaart"          => "podiumcadeaukaart",
            "sportfitcadeau"             => "sport-fitcadeau",
            "vvvgiftcard"                => "vvvgiftcard"
        ];

        if (isset($mappings[$code])) {
            return $this->getImageUrl("giftcards/{$mappings[$code]}", "svg");
        }

        return $this->getImageUrl("svg/giftcards", "svg");
    }

    /**
     * Type of the giftcard inline/redirect
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getGroupGiftcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_GROUP_GIFTCARDS, $store);
    }

    /**
     * Get Advanced order export for giftcards
     *
     * @param null|int|string $store
     * @return bool
     */
    public function hasAdvancedExportGiftcards($store = null): bool
    {
        return (bool)$this->getMethodConfigValue(self::XPATH_ACCOUNT_ADVANCED_EXPORT_GIFTCARDS, $store);
    }
}
