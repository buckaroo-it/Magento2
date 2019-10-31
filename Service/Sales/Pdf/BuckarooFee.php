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
namespace TIG\Buckaroo\Service\Sales\Pdf;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use TIG\Buckaroo\Helper\PaymentFee;
use TIG\Buckaroo\Model\Config\Source\Display\Type;
use TIG\Buckaroo\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;

class BuckarooFee extends DefaultTotal
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var PaymentFee */
    private $paymentFee;

    /**
     * {@inheritdoc}
     *
     * @param ScopeInterface $scopeConfig
     * @param PaymentFee     $paymentFee
     */
    public function __construct(
        Data $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        ScopeConfigInterface $scopeConfig,
        PaymentFee $paymentFee,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->paymentFee = $paymentFee;

        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalsForDisplay()
    {
        $store = $this->getOrder()->getStore();
        $amount = $this->getOrder()->formatPriceTxt($this->getAmount());
        $label = $this->paymentFee->getBuckarooPaymentFeeLabel($this->getOrder());
        $fontSize = $this->getFontSize() ? $this->getFontSize() : 7;

        $amountInclTax = $this->getSource()->getBuckarooFeeInclTax();

        if (!$amountInclTax) {
            $amountInclTax = $this->getAmount() + $this->getSource()->getBuckarooFeeTaxAmount();
        }

        $amountInclTax = $this->getOrder()->formatPriceTxt($amountInclTax);

        $displaySalesBuckarooFee = $this->scopeConfig->getValue(
            ConfigProviderBuckarooFee::XPATH_BUCKAROOFEE_PRICE_DISPLAY_SALES,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        switch ($displaySalesBuckarooFee) {
            case Type::DISPLAY_TYPE_BOTH:
                $totals = [
                    [
                        'amount' => $this->getAmountPrefix() . $amount,
                        'label' => __($label . ' (Excl. Tax)') . ':',
                        'font_size' => $fontSize,
                    ],
                    [
                        'amount' => $this->getAmountPrefix() . $amountInclTax,
                        'label' => __($label . ' (Incl. Tax)') . ':',
                        'font_size' => $fontSize
                    ],
                ];
                break;
            case Type::DISPLAY_TYPE_INCLUDING_TAX:
                $totals = [
                    [
                        'amount' => $this->getAmountPrefix() . $amountInclTax,
                        'label' => __($label) . ':',
                        'font_size' => $fontSize,
                    ],
                ];
                break;
            default:
                $totals = [
                    [
                        'amount' => $this->getAmountPrefix() . $amount,
                        'label' => __($label) . ':',
                        'font_size' => $fontSize,
                    ],
                ];
                break;
        }

        return $totals;
    }
}
