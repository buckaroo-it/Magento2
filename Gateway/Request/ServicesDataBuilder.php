<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ServicesDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment']->getPayment();


        $services = ['Services' => (object)[
            'Service' => [
                'Name' => 'ideal',
                'Action' => 'Pay',
                'Version' => 2,
                'RequestParameter' => [
                    [
                        '_' => $payment->getAdditionalInformation('issuer'),
                        'Name' => 'issuer',
                    ],
                ]
            ]
        ]];

        return $services;

    }
}
