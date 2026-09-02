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
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * @inheritdoc
 */
class IssuerDataBuilder implements BuilderInterface
{
    /**
     * Build the issuer request data from the payment additional information.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $issuer =  $paymentDO->getPayment()->getAdditionalInformation('issuer');

        if (empty($issuer)) {
            return [];
        }
        return [
            'issuer' => $issuer,
        ];
    }
}
