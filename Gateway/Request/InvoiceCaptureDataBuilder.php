<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;

class InvoiceCaptureDataBuilder extends AbstractDataBuilder
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    private BuckarooLog $buckarooLog;
    private BuckarooHelper $buckarooHelper;

    public function __construct(BuckarooLog $buckarooLog, BuckarooHelper $buckarooHelper)
    {
        $this->buckarooLog = $buckarooLog;
        $this->buckarooHelper = $buckarooHelper;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $capturePartial = true;

        $order = $this->getOrder();

        $totalOrder = $order->getBaseGrandTotal();
        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }
                $this->buckarooLog->addDebug(__METHOD__ . '|10|' . var_export($oInvoice->getGrandTotal(), true));
                $currentInvoice = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getGrandTotal();
            }
        }

        if ($this->buckarooHelper->areEqualAmounts($totalOrder, $currentInvoiceTotal) && $numberOfInvoices == 1) {
            $capturePartial = false; //full capture
        }

        $data['originalTransactionKey'] = $this->getPayment()->getAdditionalInformation(
            self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        $data['invoice'] = $this->getOrder()->getIncrementId();

        // Partial Capture Settings
        if ($capturePartial) {
            $data['invoice'] = $this->getOrder()->getIncrementId() . '-' . $numberOfInvoices;
            $data['originalTransactionKey'] = $this->getPayment()->getParentTransactionId();
        }

        $data['amountDebit'] = $currentInvoiceTotal;

        return $data;
    }
}
