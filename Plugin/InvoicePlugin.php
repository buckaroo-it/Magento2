<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Magento\Sales\Model\Order\Pdf\Invoice;

class InvoicePlugin
{
    /**
     * Copy Buckaroo transfer details onto each invoice before the PDF is generated.
     *
     * @param Invoice $subject
     * @param array $invoices
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
