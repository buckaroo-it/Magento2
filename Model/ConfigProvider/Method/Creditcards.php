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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;

class Creditcards extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_creditcards';

    public const XPATH_CREDITCARDS_ALLOWED_ISSUERS = 'allowed_creditcards';
    public const XPATH_USE_CARD_DESIGN             = 'card_design';

    protected array $issuers;

    /**
     * Creditcards constructor.
     *
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param Creditcard $creditcardConfigProvider
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        Creditcard $creditcardConfigProvider
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

        $this->issuers = $creditcardConfigProvider->getIssuers();
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        $issuers = $this->formatIssuers();

        return [
            'payment' => [
                'buckaroo' => [
                    'creditcards' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'creditcards'       => $issuers,
                        'defaultCardImage'  => $this->getImageUrl('svg/creditcards', 'svg'),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Add the active flag to the creditcard list. This is used in the checkout process.
     *
     * @return array
     */
    public function formatIssuers(): array
    {
        $allowed = explode(',', (string)$this->getAllowedIssuers());

        $issuers = $this->issuers;
        foreach ($issuers as $key => $issuer) {
            $issuers[$key]['active'] = in_array($issuer['code'], $allowed);
            $issuers[$key]['img'] = $this->getCreditcardLogo($issuer['code']);
        }

        return $issuers;
    }

    /**
     * Get Allowed Issuers
     *
     * @param null|int|string $store
     * @return mixed
     */
    public function getAllowedIssuers($store = null)
    {
        return $this->getMethodConfigValue(self::XPATH_CREDITCARDS_ALLOWED_ISSUERS, $store);
    }

    /**
     * Get Active Status Cm3
     *
     * @return null
     */
    public function getActiveStatusCm3()
    {
        return null;
    }
}
