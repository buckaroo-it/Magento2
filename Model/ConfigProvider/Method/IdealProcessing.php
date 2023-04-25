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
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\AllowedCurrencies;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Service\Ideal\IssuersService;
use Magento\Store\Model\ScopeInterface;

class IdealProcessing extends AbstractConfigProvider
{
    /**
     * @var IssuersService
     */
    protected IssuersService $issuersService;
    
    public const CODE = 'buckaroo_magento2_idealprocessing';

    /**
     * @var array
     */
    protected $allowedCurrencies = [
        'EUR'
    ];


    /**
     * @param Repository $assetRepo
     * @param ScopeConfigInterface $scopeConfig
     * @param AllowedCurrencies $allowedCurrencies
     * @param PaymentFee $paymentFeeHelper
     * @param IssuersService $issuersService
     */
    public function __construct(
        Repository $assetRepo,
        ScopeConfigInterface $scopeConfig,
        AllowedCurrencies $allowedCurrencies,
        PaymentFee $paymentFeeHelper,
        IssuersService $issuersService
    ) {
        $this->issuersService = $issuersService;

        parent::__construct(
            $assetRepo,
            $scopeConfig,
            $allowedCurrencies,
            $paymentFeeHelper
        );
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getConfig($store = null): array
    {
        if (!$this->getActive()) {
            return [];
        }

        $issuers = $this->issuersService->get();
        $paymentFeeLabel = $this->getBuckarooPaymentFeeLabel(self::CODE);

        $selectionType = $this->scopeConfig->getValue(
            Account::XPATH_ACCOUNT_SELECTION_TYPE,
            ScopeInterface::SCOPE_STORE
        );

        return [
            'payment' => [
                'buckaroo' => [
                    'idealprocessing' => [
                        'banks'             => $issuers,
                        'paymentFeeLabel'   => $paymentFeeLabel,
                        'subtext'           => $this->getSubtext(),
                        'subtext_style'     => $this->getSubtextStyle(),
                        'subtext_color'     => $this->getSubtextColor(),
                        'allowedCurrencies' => $this->getAllowedCurrencies(),
                        'selectionType'     => $selectionType,
                    ],
                ],
            ],
        ];
    }
}
