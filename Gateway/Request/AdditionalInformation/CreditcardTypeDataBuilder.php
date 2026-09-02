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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CreditcardTypeDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject)->getPayment();

        $cardType = $payment->getAdditionalInformation('card_type');

        if (empty($cardType)) {
            $cardType = $payment->getAdditionalInformation(BuckarooAdapter::BUCKAROO_ACTUAL_PAYMENT_METHOD);
        }

        return ['name' => $cardType];
    }
}
