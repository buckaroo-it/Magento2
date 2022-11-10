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

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;

class Giftcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_giftcards';

    public const XPATH_GIFTCARDS_ALLOWED_GIFTCARDS    = 'payment/buckaroo_magento2_giftcards/allowed_giftcards';
    public const XPATH_GIFTCARDS_GROUP_GIFTCARDS      = 'payment/buckaroo_magento2_giftcards/group_giftcards';
    public const XPATH_GIFTCARDS_SORT                 = 'payment/buckaroo_magento2_giftcards/sorted_giftcards';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR',
    ];

    /** @var StoreManagerInterface */
    private $storeManager;

    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $sorted = explode(',', (string)$this->getSort());

        if (!empty($sorted)) {
            $sortedPosition = 1;
            foreach ($sorted as $cardName) {
                $sortedArray[$cardName] = $sortedPosition++;
            }
        }

        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Giftcards::PAYMENT_METHOD_CODE
        );

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource      = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
        $connection    = $resource->getConnection();
        $tableName     = $resource->getTableName('buckaroo_magento2_giftcard');
        $result        = $connection->fetchAll("SELECT * FROM " . $tableName);
        foreach ($result as $item) {
            $item['sort'] = isset($sortedArray[$item['label']]) ? $sortedArray[$item['label']] : '99';
            $allGiftCards[$item['servicecode']] = $item;
        }

        $availableCards = $this->getAllowedGiftcards();

        $url = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );

        foreach (explode(',', (string)$availableCards) as $value) {
            $cards[] = [
                'code'  => $value,
                'title' => isset($allGiftCards[$value]['label']) ? $allGiftCards[$value]['label'] : '',
                'logo'  => isset($allGiftCards[$value]['logo']) ? $url . $allGiftCards[$value]['logo'] : false,
                'sort'  => isset($allGiftCards[$value]['sort']) ? $allGiftCards[$value]['sort'] : '99',
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
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAllowedGiftcards($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritDoc
     */
    public function getGroupGiftcards($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_GROUP_GIFTCARDS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @inheritDoc
     */
    public function getSort($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_SORT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
