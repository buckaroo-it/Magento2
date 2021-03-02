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
namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Invoice;

class KlarnainDiscountPartialInformation extends KlarnaDiscountPartialInformation
{
    /**
     * @return bool
     * @throws \LogicException
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function shouldShowWarning()
    {
        $invoice = $this->getInvoice();

        $order = $invoice->getOrder();

        $payment = $order->getPayment();

        /**
         * The warning should only be shown for partial invoices
         */
        if ($payment->canCapturePartial()) {
            return false;
        }

        /**
         * The warning should only be shown for Klarna Buckaroo payment methods.
         */
        $paymentMethod = $payment->getMethod();
        if (strpos($paymentMethod, 'buckaroo_magento2_klarnain') === false) {
            return false;
        }

        return true;
    }
}