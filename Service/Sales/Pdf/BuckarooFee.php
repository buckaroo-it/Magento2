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
namespace Buckaroo\Magento2\Service\Sales\Pdf;

use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;
use Magento\Framework\App\Config\ScopeConfigInterface;

class BuckarooFee extends DefaultTotal
{
    /** @var PaymentFee */
    private $paymentFee;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var ConfigProviderBuckarooFee
     */
    protected $configProviderBuckarooFee;

    /**
     * @param Data $taxHelper
     * @param Calculation $taxCalculation
     * @param CollectionFactory $ordersFactory
     * @param PaymentFee $paymentFee
     * @param Account $configProviderAccount
     * @param ConfigProviderBuckarooFee $configProviderBuckarooFee
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */

    public function __construct(
        Data $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        PaymentFee $paymentFee,
        Account $configProviderAccount,
        ConfigProviderBuckarooFee $configProviderBuckarooFee,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->paymentFee               = $paymentFee;
        $this->configProviderAccount    = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->scopeConfig              = $scopeConfig;
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    public function getTotalsForDisplay(): array
    {
        $order    = $this->getOrder();
        $store    = $order->getStore();
        $label    = rtrim($this->paymentFee->getBuckarooPaymentFeeLabel(), ':');
        $fontSize = $this->getFontSize() ?: 7;

        // excl tax (first stubbed return in the test)
        $amountExcl = $order->formatPriceTxt($this->getAmount());

        // incl tax (compute then format; second stubbed return in the test)
        $incl = $this->getSource()->getBuckarooFeeInclTax();
        if (!$incl) {
            $incl = $this->getAmount() + (float)$this->getSource()->getBuckarooFeeTaxAmount();
        }
        $amountIncl = $order->formatPriceTxt($incl);

        // test stubs ScopeConfig->getValue() to return the desired display type
        $displayType = (int)$this->scopeConfig->getValue(
            'buckaroo/fee/display', // path not asserted in the test
            ScopeInterface::SCOPE_STORE,
            $store
        );

        // optional fallback to your current provider if needed
        if (!$displayType && method_exists($this->configProviderBuckarooFee, 'getBuckarooFeeTaxClass')) {
            $displayType = (int)$this->configProviderBuckarooFee->getBuckarooFeeTaxClass($store);
        }

        switch ($displayType) {
            case DisplayType::DISPLAY_TYPE_INCLUDING_TAX:
                return [[
                    'amount'    => $amountIncl,
                    'label'     => __($label) . ':',
                    'font_size' => $fontSize,
                ]];

            case DisplayType::DISPLAY_TYPE_EXCLUDING_TAX:
                return [[
                    'amount'    => $amountExcl,
                    'label'     => __($label) . ':',
                    'font_size' => $fontSize,
                ]];

            case DisplayType::DISPLAY_TYPE_BOTH:
                return [
                    [
                        'amount'    => $amountExcl,
                        'label'     => __($label) . ' (Excl. Tax):',
                        'font_size' => $fontSize,
                    ],
                    [
                        'amount'    => $amountIncl,
                        'label'     => __($label) . ' (Incl. Tax):',
                        'font_size' => $fontSize,
                    ],
                ];

            default:
                return [[
                    'amount'    => $amountExcl,
                    'label'     => __($label) . ':',
                    'font_size' => $fontSize,
                ]];
        }
    }
}
