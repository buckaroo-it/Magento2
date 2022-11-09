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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Mrcash extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_mrcash';

    public const XPATH_MRCASH_USE_CLIENT_SIDE = 'payment/buckaroo_magento2_mrcash/client_side';

    public const MRCASH_REDIRECT_URL = '/buckaroo/mrcash/pay';

    /** @var FormKey */
    private FormKey $formKey;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param FormKey              $formKey
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        FormKey $formKey
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->formKey = $formKey;
    }

    /**
     * Get Magento Form Key
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(
            \Buckaroo\Magento2\Model\Method\Mrcash::PAYMENT_METHOD_CODE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'mrcash' => [
                        'paymentFeeLabel' => $paymentFeeLabel,
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'useClientSide' => (int) $this->useClientSide(),
                        'redirecturl' => self::MRCASH_REDIRECT_URL . '?form_key=' . $this->getFormKey()
                    ],
                ],
            ],
        ];
    }

    /**
     * Get Use Client Side
     *
     * @param null|int|string $store
     * @return mixed
     */
    private function useClientSide($store = null)
    {
        return $this->scopeConfig->getValue(
            static::XPATH_MRCASH_USE_CLIENT_SIDE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
