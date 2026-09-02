<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;

class Mrcash extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_mrcash';

    public const XPATH_MRCASH_USE_CLIENT_SIDE = 'client_side';

    public const MRCASH_REDIRECT_URL = '/buckaroo/mrcash/pay';
    public const XPATH_MRCASH_PAYMENT_FEE              = 'payment/buckaroo_magento2_mrcash/payment_fee';

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param LogoService          $logoService
     * @param FormKey              $formKey
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        FormKey $formKey
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);

        $this->formKey = $formKey;
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     * @throws LocalizedException
     */
    public function getConfig(): array
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'useClientSide' => (int)$this->useClientSide(),
            'redirecturl'   => self::MRCASH_REDIRECT_URL . '?form_key=' . $this->getFormKey(),
        ]);
    }

    /**
     * Get Use Client Side
     *
     * @param null|int|string $store
     *
     * @return mixed
     */
    public function useClientSide($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_MRCASH_USE_CLIENT_SIDE, $store);
    }

    /**
     * Get Magento Form Key
     *
     * @throws LocalizedException
     *
     * @return string
     */
    private function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get the configured payment fee for this payment method.
     *
     * @param null|int $storeId
     *
     * @return float
     */
    public function getPaymentFee($storeId = null)
    {
        $paymentFee = $this->scopeConfig->getValue(
            self::XPATH_MRCASH_PAYMENT_FEE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $paymentFee ?: 0;
    }
}
