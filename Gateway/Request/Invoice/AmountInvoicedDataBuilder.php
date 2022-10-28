<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\Invoice\AbstractInvoiceDataBuilder;

class AmountInvoicedDataBuilder extends AbstractInvoiceDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return ['amountDebit' => $this->currentInvoiceTotal ?? 0];
    }
}
