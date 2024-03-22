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
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Magento\Framework\Data\Form\FormKey;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Mrcash extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_mrcash';

    public const XPATH_MRCASH_USE_CLIENT_SIDE = 'client_side';

    public const MRCASH_REDIRECT_URL = '/buckaroo/mrcash/pay';

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param FormKey $formKey
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
    public function getConfig()
    {
       if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'useClientSide'     => (int)$this->useClientSide(),
            'redirecturl'       => self::MRCASH_REDIRECT_URL . '?form_key=' . $this->getFormKey(),
        ]);
    }

    /**
     * Get Use Client Side
     *
     * @param null|int|string $store
     * @return mixed
     */
    private function useClientSide($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_MRCASH_USE_CLIENT_SIDE, $store);
    }

    /**
     * Get Magento Form Key
     *
     * @return string
     * @throws LocalizedException
     */
    private function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
