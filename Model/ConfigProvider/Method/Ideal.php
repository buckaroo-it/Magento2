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

class Ideal extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_ideal';

    public const XPATH_IDEAL_SELECTION_TYPE = 'buckaroo_magento2/account/selection_type';
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
            \Buckaroo\Magento2\Model\Method\Ideal::PAYMENT_METHOD_CODE
        );

        $selectionType = $this->getSelectionType();

        return [
            'payment' => [
                'buckaroo' => [
                    'ideal' => [
                        'banks' => $issuers,
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType' => $selectionType,
                    ],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSelectionType($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_IDEAL_SELECTION_TYPE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
