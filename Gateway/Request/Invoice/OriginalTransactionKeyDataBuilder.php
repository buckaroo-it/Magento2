<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\Invoice\AbstractInvoiceDataBuilder;

class OriginalTransactionKeyDataBuilder extends AbstractInvoiceDataBuilder
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $data['originalTransactionKey'] = $this->getPayment()->getAdditionalInformation(
            self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        // Partial Capture Settings
        if ($this->capturePartial) {
            $data['originalTransactionKey'] = $this->getPayment()->getParentTransactionId();
        }

        return $data;
    }
}
