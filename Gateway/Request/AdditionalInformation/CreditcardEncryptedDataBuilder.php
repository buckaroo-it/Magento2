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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CreditcardEncryptedDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $additionalInformation = $payment->getAdditionalInformation();

        if (!isset($additionalInformation['customer_encrypteddata'])) {
            throw new Exception(__(
                'An error occured trying to send the encrypted creditcard data to Buckaroo.'
            ));
        }

        if (!isset($additionalInformation['customer_creditcardcompany'])) {
            throw new Exception(__(
                'An error occured trying to send the creditcard company data to Buckaroo.'
            ));
        }

        return [
            'name' => $additionalInformation['customer_creditcardcompany'],
            'sessionId' => $additionalInformation['customer_encrypteddata']
        ];
    }
}
