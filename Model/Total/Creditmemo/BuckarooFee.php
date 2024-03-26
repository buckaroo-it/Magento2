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

namespace Buckaroo\Magento2\Model\Total\Creditmemo;

use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

class BuckarooFee extends AbstractTotal
{
    /**
     * Request instance
     *
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    /**
     * Collect totals for credit memo
     *
     * @param Creditmemo $creditmemo
     * @return $this
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function collect(Creditmemo $creditmemo): BuckarooFee
    {
        $order = $creditmemo->getOrder();
        $invoice = $creditmemo->getInvoice();

        $method = $order->getPayment()->getMethod();
        $order->getCreditmemosCollection();

        $salesModel = ($invoice ?: $order);

        $refundItem = $this->request->getPost('creditmemo');

        if ($salesModel->getBaseBuckarooFee()
            && $order->getBaseBuckarooFeeInvoiced() > $order->getBaseBuckarooFeeRefunded()
        ) {
            $baseBuckarooFee = $salesModel->getBaseBuckarooFee();
            $buckarooFee = $salesModel->getBuckarooFee();

            if (!isset($refundItem['buckaroo_fee_refundable']) && !empty($refundItem)) {
                $baseBuckarooFee = 0;
                $buckarooFee = 0;
                if (preg_match('/afterpay/', $method)) {
                    $creditmemo->setTaxAmount($creditmemo->getTaxAmount() - $creditmemo->getBuckarooFeeTaxAmount());
                    $creditmemo->setBaseTaxAmount(
                        $creditmemo->getBaseTaxAmount() - $creditmemo->getBuckarooFeeBaseTaxAmount()
                    );

                    $creditmemo->setBaseGrandTotal(
                        $creditmemo->getBaseGrandTotal() -
                        $creditmemo->getBuckarooFeeBaseTaxAmount()
                    );
                    $creditmemo->setGrandTotal(
                        $creditmemo->getGrandTotal() -
                        $creditmemo->getBuckarooFeeTaxAmount()
                    );

                    $creditmemo->setBuckarooFeeBaseTaxAmount(0);
                    $creditmemo->setBuckarooFeeTaxAmount(0);
                    $creditmemo->setBuckarooFeeInclTax(0);
                    $creditmemo->setBaseBuckarooFeeInclTax(0);

                    $order->setBuckarooFeeBaseTaxAmountRefunded(0);
                    $order->setBuckarooFeeTaxAmountRefunded(0);
                }
            }

            $order->setBaseBuckarooFeeRefunded($order->getBaseBuckarooFeeRefunded() + $baseBuckarooFee);
            $order->setBuckarooFeeRefunded($order->getBuckarooFeeRefunded() + $buckarooFee);

            $creditmemo->setBaseBuckarooFee($baseBuckarooFee);
            $creditmemo->setBuckarooFee($buckarooFee);

            if (preg_match('/afterpay/', $method)) {
                if (!isset($refundItem['buckaroo_fee_refundable']) && !empty($refundItem)) {
                    return $this;
                }
            }
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $creditmemo->setBaseGrandTotal(
            $creditmemo->getBaseGrandTotal() +
            $creditmemo->getBaseBuckarooFee()
        );
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $creditmemo->setGrandTotal(
            $creditmemo->getGrandTotal() +
            $creditmemo->getBuckarooFee()
        );

        return $this;
    }
}
