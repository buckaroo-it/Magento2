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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;

class Payconiq extends AbstractConfigProvider
{
    public const CODE = 'buckaroo_magento2_payconiq';

    public const PAYCONIC_REDIRECT_URL = '/buckaroo/payconiq/pay';

    /** @var FormKey */
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
        FormKey $formKey
    ) {
        parent::__construct($assetRepo, $scopeConfig, $allowedCurrencies, $paymentFeeHelper);

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
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        return [
            'payment' => [
                'buckaroo' => [
                    'payconiq' => [
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'redirecturl'       => static::PAYCONIC_REDIRECT_URL . '?form_key=' . $this->getFormKey()
                    ],
                ],
            ],
        ];
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
