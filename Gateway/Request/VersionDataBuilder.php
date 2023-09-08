<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class VersionDataBuilder implements BuilderInterface
{
    private BuilderInterface $v1;
    private BuilderInterface $v2;

    public function __construct(
        BuilderInterface $v1,
        BuilderInterface $v2
    ) {
        $this->v1 = $v1;
        $this->v2 = $v2;
    }

    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $apiVersion = $payment->getMethodInstance()->getConfigData('api_version');

        if (!empty($apiVersion)) {
            if (strtolower($apiVersion) == 'v2') {
                return $this->v2->build($buildSubject);
            } else {
                return $this->v1->build($buildSubject);
            }
        }

        return [];
    }
}