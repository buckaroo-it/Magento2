<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Emandate as EmandateConfig;
use Magento\Sales\Model\Order;

class EmandateDataBuilder implements BuilderInterface
{
    /**
     * @var EmandateConfig
     */
    protected EmandateConfig $config;

    /**
     * @param EmandateConfig $config
     */
    public function __construct(EmandateConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        return [
            'emandatereason'    => (string)$this->config->getReason(),
            'sequencetype'      => (float)$this->config->getSequenceType(),
            'purchaseid'        => $order->getIncrementId(),
            'debtorbankid'      => (string)$paymentDO->getPayment()->getAdditionalInformation('issuer'),
            'debtorreference'   => $this->getEmail($order),
            'language'          => (string)$this->config->getLanguage(),
        ];
    }

    /**
     * Get email from billingAddress
     *
     * @param Order $order
     * @return string
     */
    protected function getEmail($order): string
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress !== null) {
            return (string)$billingAddress->getEmail();
        }

        return '';
    }
}
