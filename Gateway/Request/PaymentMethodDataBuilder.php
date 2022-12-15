<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentMethodDataBuilder implements BuilderInterface
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

        /**
         * @var \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
         */
        $methodInstance = $payment->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode ?? 'buckaroo_magento2_ideal';
        $providerType = str_replace('buckaroo_magento2_', '', $method);

        if ($providerType === 'capayablein3') {
            $providerType = 'Capayable';
        }
        return [
            'payment_method' => $providerType,
        ];
    }
}
