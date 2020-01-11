<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Model\Total\Creditmemo\Tax;

class BuckarooFee extends \Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal
{
    /**
     * Collect totals for credit memo
     *
     * @param  \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $invoice = $creditmemo->getInvoice();

        $salesModel = ($invoice ? $invoice : $order);

        if ($salesModel->getBuckarooFeeBaseTaxAmount()
            && $order->getBuckarooFeeBaseTaxAmountInvoiced() > $order->getBuckarooFeeBaseTaxAmountRefunded()
        ) {
            $baseBuckarooFeeTax = $salesModel->getBuckarooFeeBaseTaxAmount();
            $buckarooFeeTax = $salesModel->getBuckarooFeeTaxAmount();

            $order->setBuckarooFeeBaseTaxAmountRefunded(
                $order->getBuckarooFeeBaseTaxAmountRefunded() +  $baseBuckarooFeeTax
            );
            $order->setBuckarooFeeTaxAmountRefunded($order->getBuckarooFeeTaxAmountRefunded() + $buckarooFeeTax);

            $creditmemo->setBuckarooFeeBaseTaxAmount($baseBuckarooFeeTax);
            $creditmemo->setBuckarooFeeTaxAmount($buckarooFeeTax);

            $buckarooFeeInclTax = $salesModel->getBuckarooFeeInclTax();
            $baseBuckarooFeeInclTax = $salesModel->getBaseBuckarooFeeInclTax();

            $order->setBuckarooFeeInclTaxRefunded($order->getBuckarooFeeInclTaxRefunded() + $buckarooFeeInclTax);
            $order->setBaseBuckarooFeeInclTaxRefunded(
                $order->getBaseBuckarooFeeInclTaxRefunded() + $baseBuckarooFeeInclTax
            );

            $creditmemo->setBuckarooFeeInclTax($buckarooFeeInclTax);
            $creditmemo->setBaseBuckarooFeeInclTax($baseBuckarooFeeInclTax);

            // Partial refunds are OK, magento did not add the payment fee tax yet so we do it
            // Full refunds there is double payment fee tax, because magento already added the tax
            // We check if the tax is not more than it should be..
            if ($creditmemo->getBaseTaxAmount() < $order->getBaseTaxAmount()) {
                $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseBuckarooFeeTax);
                $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $buckarooFeeTax);

                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseBuckarooFeeTax);
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $buckarooFeeTax);
            }
        }

        return $this;
    }
}
