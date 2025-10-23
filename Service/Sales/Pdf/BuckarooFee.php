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
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as ConfigProviderBuckarooFee;

class BuckarooFee extends DefaultTotal
{
    /** @var PaymentFee */
    private $paymentFee;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var ConfigProviderBuckarooFee
     */
    protected $configProviderBuckarooFee;

    /**
     * @param Data                      $taxHelper
     * @param Calculation               $taxCalculation
     * @param CollectionFactory         $ordersFactory
     * @param PaymentFee                $paymentFee
     * @param Account                   $configProviderAccount
     * @param ConfigProviderBuckarooFee $configProviderBuckarooFee
     * @param array                     $data
     */
    public function __construct(
        Data $taxHelper,
        Calculation $taxCalculation,
        CollectionFactory $ordersFactory,
        PaymentFee $paymentFee,
        Account $configProviderAccount,
        ConfigProviderBuckarooFee $configProviderBuckarooFee,
        array $data = []
    ) {
        $this->paymentFee = $paymentFee;
        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalsForDisplay()
    {
        $store = $this->getOrder()->getStore();
        $amount = $this->getOrder()->formatPriceTxt($this->getAmount());
        $label = $this->paymentFee->getBuckarooPaymentFeeLabel();
        $fontSize = $this->getFontSize() ? $this->getFontSize() : 7;

        $isFeeInclusiveOfTax = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($store);

        $amountInclTax = $this->getSource()->getBuckarooFeeInclTax();

        if (!$amountInclTax) {
            $amountInclTax = $this->getAmount() + $this->getSource()->getBuckarooFeeTaxAmount();
        }

        $amountInclTax = $this->getOrder()->formatPriceTxt($amountInclTax);

        if ($isFeeInclusiveOfTax == DisplayType::DISPLAY_TYPE_INCLUDING_TAX) {
            $totals = [
                [
                    'amount' => $this->getAmountPrefix() . $amountInclTax,
                    'label' => __($label) . ':',
                    'font_size' => $fontSize,
                ],
            ];
        } else {
            $totals = [
                [
                    'amount' => $this->getAmountPrefix() . $amount,
                    'label' => __($label) . ':',
                    'font_size' => $fontSize,
                ],
            ];
        }

        return $totals;
    }
}
