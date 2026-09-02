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

namespace Buckaroo\Magento2\Gateway\Request\Invoice;

class InvoiceNumberDataBuilder extends AbstractInvoiceDataBuilder
{
    /**
     * @inheritdoc
     */
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
