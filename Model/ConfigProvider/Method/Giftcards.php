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
use Buckaroo\Magento2\Model\Config\Source\Giftcards as GiftcardsSource;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\CollectionFactory as GiftcardCollectionFactory;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Giftcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_giftcards';

    public const XPATH_GIFTCARDS_ALLOWED_GIFTCARDS       = 'allowed_giftcards';
    public const XPATH_GIFTCARDS_GROUP_GIFTCARDS         = 'group_giftcards';
    public const XPATH_GIFTCARDS_SORTED_GIFTCARDS        = 'sorted_giftcards';
    public const XPATH_ACCOUNT_ADVANCED_EXPORT_GIFTCARDS = 'buckaroo_magento2/account/advanced_export_giftcards';
    public const XPATH_GIFTCARDS_PAYMENT_FEE          = 'payment/buckaroo_magento2_giftcards/payment_fee';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
    ];

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GiftcardCollectionFactory
     */
    private $giftcardCollectionFactory;

    /**
     * @var GiftcardsSource
     */
    private $giftcardsSource;

    /**
     * @param Repository                $assetRepo
     * @param ScopeConfigInterface      $scopeConfig
     * @param AllowedCurrencies         $allowedCurrencies
     * @param PaymentFee                $paymentFeeHelper
     * @param LogoService               $logoService
     * @param StoreManagerInterface     $storeManager
     * @param ResourceConnection        $resourceConnection
     * @param GiftcardsSource           $giftcardsSource
     * @param GiftcardCollectionFactory $giftcardCollectionFactory
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        StoreManagerInterface $storeManager,
        GiftcardCollectionFactory $giftcardCollectionFactory,
        GiftcardsSource $giftcardsSource
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);
        $this->storeManager = $storeManager;
        $this->giftcardCollectionFactory = $giftcardCollectionFactory;
        $this->giftcardsSource = $giftcardsSource;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @throws Exception|NoSuchEntityException
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'groupGiftcards'     => (int)$this->getGroupGiftcards() === 1,
            'availableGiftcards' => $this->getAvailableGiftcards(),
        ]);
    }

    /**
     * Type of the giftcard inline/redirect
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function getGroupGiftcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_GROUP_GIFTCARDS, $store);
    }

    public function getAvailableGiftcards()
    {
        $sort = (string)$this->getSortedGiftcards();
        $sortedArray = [];

        if (!empty($sort)) {
            $sorted = explode(',', $sort);
            $sortedPosition = 1;
            foreach ($sorted as $cardName) {
                $sortedArray[$cardName] = $sortedPosition++;
            }
        }

        // Use proper collection instead of raw SQL
        $giftcardCollection = $this->giftcardCollectionFactory->create();
        
        $allGiftCards = [];
        foreach ($giftcardCollection as $giftcard) {
            $servicecode = $giftcard->getServicecode();
            $allGiftCards[$servicecode] = [
                'servicecode' => $servicecode,
                'label' => $giftcard->getLabel(),
                'logo' => $giftcard->getLogo(),
                'sort' => $sortedArray[$servicecode] ?? '99'
            ];
        }

        $availableCards = $this->getAllowedGiftcards();

        $cards = [];
        if (!empty($availableCards)) {
            $url = $this->storeManager->getStore()->getBaseUrl(
                UrlInterface::URL_TYPE_MEDIA
            );

            foreach (explode(',', (string)$availableCards) as $value) {
                $logo = $this->getGiftcardLogo($value);
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
        }

        return $cards;
    }

    /**
     * Get Allowed Giftcards
     *
     * @param $store
     *
     * @return mixed|null
     */
    public function getAllowedGiftcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS, $store);
    }

    /**
     * Get Sorted Giftcards
     *
     * @param $store
     *
     * @return mixed|null
     */
    public function getSortedGiftcards($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_GIFTCARDS_SORTED_GIFTCARDS, $store);
    }

    /**
     * Get Sorted Issuers (alias for getSortedGiftcards for SortIssuers block compatibility)
     *
     * @param $store
     *
     * @return mixed|null
     */
    public function getSortedIssuers($store = null)
    {
        $sorted = $this->getSortedGiftcards($store);

        // Handle empty placeholder - return empty string instead of __EMPTY__
        if ($sorted === '__EMPTY__') {
            return '';
        }

        return $sorted;
    }

    /**
     * Get all available giftcard issuers for the SortIssuers block
     * Uses the same source model as the admin multiselect to ensure consistency
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getAllIssuers(): array
    {
        // Get only allowed giftcards
        $allowedCards = $this->getAllowedGiftcards();
        if (empty($allowedCards)) {
            return [];
        }

        $allowedCodesArray = explode(',', (string)$allowedCards);

        // Remove any empty values from the array
        $allowedCodesArray = array_filter($allowedCodesArray, function ($value) {
            return !empty(trim($value));
        });

        if (empty($allowedCodesArray)) {
            return [];
        }

        // Use the same source model as the admin multiselect
        $allGiftcards = $this->giftcardsSource->toOptionArray();

        $issuers = [];

        foreach ($allGiftcards as $giftcard) {
            $code = $giftcard['value'];
            $name = $giftcard['label'];

            // Skip empty values or "no giftcards" message
            if (empty($code) || strpos($name, 'You have not yet added') !== false) {
                continue;
            }

            // Only include if this giftcard is in the allowed list
            if (!in_array($code, $allowedCodesArray)) {
                continue;
            }

            $logo = $this->getGiftcardLogo($code);

            // Try to get custom logo from collection
            $giftcardCollection = $this->giftcardCollectionFactory->create();
            $giftcardCollection->addFieldToFilter('servicecode', $code);
            $giftcard = $giftcardCollection->getFirstItem();
            
            if ($giftcard->getId() && $giftcard->getLogo()) {
                $logo = $this->storeManager->getStore()->getBaseUrl(
                    UrlInterface::URL_TYPE_MEDIA
                ) . $giftcard->getLogo();
            }

            $issuers[$code] = [
                'code' => $code,
                'name' => $name,
                'img' => $logo
            ];
        }

        return $issuers;
    }

    /**
     * Format issuers for display
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function formatIssuers(): array
    {
        return $this->getAllIssuers();
    }

    /**
     * Get giftcard logo image
     *
     * @param string $code
     *
     * @return string
     */
    protected function getGiftcardLogo(string $code): string
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
     * Get Advanced order export for giftcards
     *
     * @param null|int|string $store
     *
     * @return bool
     */
    public function hasAdvancedExportGiftcards($store = null): bool
    {
        return (bool)$this->getMethodConfigValue(self::XPATH_ACCOUNT_ADVANCED_EXPORT_GIFTCARDS, $store);
    }

    /**
     * @param null|int $storeId
     *
     * @return bool|float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_GIFTCARDS_PAYMENT_FEE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
