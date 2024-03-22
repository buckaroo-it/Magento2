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

namespace Buckaroo\Magento2\Model\ConfigProvider\Method;

use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Service\LogoService;
use Magento\Framework\View\Asset\Repository;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;

class Billink extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_billink';

    public const XPATH_BILLINK_BUSINESS  = 'business';

    /**
     * @var BuckarooHelper
     */
    private BuckarooHelper $helper;

    /**
     * @param Repository           $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies    $allowedCurrencies
     * @param PaymentFee           $paymentFeeHelper
     * @param BuckarooHelper       $helper
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        LogoService $logoService,
        BuckarooHelper $helper
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper, $logoService);

        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        if (!$this->getActive()) {
            return [];
        }

        return $this->fullConfig([
            'sendEmail'         => $this->hasOrderEmail(),
            'b2b'               => $this->helper->checkCustomerGroup('buckaroo_magento2_billink'),
            'genderList'        => [
                ['genderType' => 'male', 'genderTitle' => __('He/him')],
                ['genderType' => 'female', 'genderTitle' => __('She/her')],
                ['genderType' => 'unknown', 'genderTitle' => __('They/them')],
                ['genderType' => 'unknown', 'genderTitle' => __('I prefer not to say')]
            ],
            'businessMethod'    => $this->getBusiness(),
            'showFinancialWarning' => $this->canShowFinancialWarning(),
        ]);
    }

    /**
     * Get Customer Type
     * businessMethod 1 = B2C
     * businessMethod 2 = B2B
     *
     * @return bool|int
     */
    public function getBusiness()
    {
        $business = (int)$this->getMethodConfigValue(self::XPATH_BILLINK_BUSINESS);

        return $business ?: false;
    }
}
