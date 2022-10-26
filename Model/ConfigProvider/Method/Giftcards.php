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
    const XPATH_GIFTCARDS_PAYMENT_FEE          = 'payment/buckaroo_magento2_giftcards/payment_fee';
    const XPATH_GIFTCARDS_PAYMENT_FEE_LABEL    = 'payment/buckaroo_magento2_giftcards/payment_fee_label';
    const XPATH_GIFTCARDS_ACTIVE               = 'payment/buckaroo_magento2_giftcards/active';
    const XPATH_GIFTCARDS_ACTIVE_STATUS        = 'payment/buckaroo_magento2_giftcards/active_status';
    const XPATH_GIFTCARDS_ORDER_STATUS_SUCCESS = 'payment/buckaroo_magento2_giftcards/order_status_success';
    const XPATH_GIFTCARDS_ORDER_STATUS_FAILED  = 'payment/buckaroo_magento2_giftcards/order_status_failed';
    const XPATH_GIFTCARDS_ORDER_EMAIL          = 'payment/buckaroo_magento2_giftcards/order_email';
    const XPATH_GIFTCARDS_AVAILABLE_IN_BACKEND = 'payment/buckaroo_magento2_giftcards/available_in_backend';
    const XPATH_GIFTCARDS_ALLOWED_GIFTCARDS    = 'payment/buckaroo_magento2_giftcards/allowed_giftcards';
    const XPATH_GIFTCARDS_GROUP_GIFTCARDS      = 'payment/buckaroo_magento2_giftcards/group_giftcards';
    const XPATH_GIFTCARDS_SORT                 = 'payment/buckaroo_magento2_giftcards/sorted_giftcards';

    const XPATH_ALLOWED_CURRENCIES = 'payment/buckaroo_magento2_giftcards/allowed_currencies';

    const XPATH_ALLOW_SPECIFIC   = 'payment/buckaroo_magento2_giftcards/allowspecific';
    const XPATH_SPECIFIC_COUNTRY = 'payment/buckaroo_magento2_giftcards/specificcountry';
    const XPATH_SPECIFIC_CUSTOMER_GROUP = 'payment/buckaroo_magento2_giftcards/specificcustomergroup';

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
        if (!$this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ACTIVE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        )) {
            return [];
        }

        $sorted = explode(',', (string)$this->scopeConfig->getValue(
            self::XPATH_GIFTCARDS_SORT,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));

        if (!empty($sorted)) {
            $sortedPosition = 1;
            foreach ($sorted as $cardName) {
                $sorted_array[$cardName] = $sortedPosition++;
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
            $item['sort'] = isset($sorted_array[$item['label']]) ? $sorted_array[$item['label']] : '99';
            $allGiftCards[$item['servicecode']] = $item;
        }

        $availableCards = $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $url = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );

        foreach (explode(',', (string)$availableCards) as $key => $value) {
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
                    'groupGiftcards'   => $this->scopeConfig->getValue(
                        static::XPATH_GIFTCARDS_GROUP_GIFTCARDS,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
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
     * @param null|int $storeId
     *
     * @return bool|float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ? $paymentFee : false;
    }

    public function getAllowedCards($storeId = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentFeeLabel($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_PAYMENT_FEE_LABEL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActive($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveStatus($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ACTIVE_STATUS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusSuccess($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ORDER_STATUS_SUCCESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderStatusFailed($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ORDER_STATUS_FAILED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_ORDER_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableInBackend($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_GIFTCARDS_AVAILABLE_IN_BACKEND,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
