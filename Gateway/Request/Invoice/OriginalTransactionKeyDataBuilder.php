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

class OriginalTransactionKeyDataBuilder extends AbstractInvoiceDataBuilder
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';

    /**
     * @inheritdoc
     */
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
