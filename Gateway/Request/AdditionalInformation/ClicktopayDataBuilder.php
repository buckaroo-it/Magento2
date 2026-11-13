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

class ClicktopayDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment   = $paymentDO->getPayment();

        $transientToken = $payment->getAdditionalInformation('transient_token');
        $identifier     = $payment->getAdditionalInformation('identifier');

        if (empty($transientToken)) {
            throw new \InvalidArgumentException('Click to Pay transient token is missing from payment data.');
        }

        return [
            'transientToken' => $transientToken,
            'identifier'     => (string) $identifier,
        ];
    }
}
