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

namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Model\Order\Pdf\Invoice;

class InvoicePlugin
{
    /**
     * @param  Invoice $subject
     * @param          $invoices
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeGetPdf(Invoice $subject, $invoices)
    {
        foreach ($invoices as $invoice) {
            /** @var \Magento\Sales\Model\Order\Invoice $invoice */
            $transferDetails = $invoice->getOrder()->getPayment()->getAdditionalInformation('transfer_details');

            if (!empty($transferDetails) && is_array($transferDetails)) {
                foreach ($transferDetails as $key => $transferDetail) {
                    $invoice->setData($key, $transferDetail);
                }
            }
        }

        return [$invoices];
    }
}
