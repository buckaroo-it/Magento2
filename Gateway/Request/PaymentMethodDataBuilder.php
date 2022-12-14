<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentMethodDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /**
         * @var \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
         */
        $methodInstance = $paymentDO->getPayment()->getMethodInstance();
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
