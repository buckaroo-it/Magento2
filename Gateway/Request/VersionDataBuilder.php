<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Payment\Gateway\Request\BuilderInterface;

class VersionDataBuilder implements BuilderInterface
{
    /**
     * @var BuilderInterface
     */
    private $v1;

    /**
     * @var BuilderInterface
     */
    private $v2;

    /**
     * Constructor
     *
     * @param BuilderInterface $v1
     * @param BuilderInterface $v2
     */
    public function __construct(
        BuilderInterface $v1,
        BuilderInterface $v2
    ) {
        $this->v1 = $v1;
        $this->v2 = $v2;
    }

    /**
     * Delegate request building to the v1 or v2 builder based on the configured API version.
     *
     * @param array $buildSubject
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        if ($payment->getMethodInstance()->getCode() === CapayableIn3::CODE) {
            return $this->v1->build($buildSubject);
        }

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
