<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentMethodDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        /**
         * @var \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
         */
        $method = $paymentDO->getPayment()->getMethodInstance()->getCode() ?? 'buckaroo_magento2_ideal';

        $providerType = str_replace('buckaroo_magento2_', '', $method);

        if ($providerType === 'capayablein3') {
            $providerType = 'Capayable';
        }
        return [
            'payment_method' => $providerType,
        ];
    }
}
