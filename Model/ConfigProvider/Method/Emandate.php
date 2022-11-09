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

use Magento\Store\Model\ScopeInterface;

class Emandate extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_emandate';

    public const XPATH_EMANDATE_SEQUENCE_TYPE         = 'payment/buckaroo_magento2_emandate/sequence_type';
    public const XPATH_EMANDATE_REASON                = 'payment/buckaroo_magento2_emandate/reason';
    public const XPATH_EMANDATE_LANGUAGE              = 'payment/buckaroo_magento2_emandate/language';

    /**
     * @var array
     */
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Knab Bank',
            'code' => 'KNABNL2H',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
    ];

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        $issuers = $this->formatIssuers();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Emandate::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'emandate' => [
                        'banks' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceType($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_SEQUENCE_TYPE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getReason($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_REASON,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_EMANDATE_LANGUAGE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
