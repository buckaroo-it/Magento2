<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

use Buckaroo\Magento2\Gateway\Request\Invoice\AbstractInvoiceDataBuilder;

class InvoiceNumberDataBuilder extends AbstractInvoiceDataBuilder
{
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $data['invoice'] = $this->getOrder()->getIncrementId();

        // Partial Capture Settings
        if ($this->capturePartial) {
            $data['invoice'] = $this->getOrder()->getIncrementId() . '-' . $this->numberOfInvoices;
        }

        return $data;
    }
}
