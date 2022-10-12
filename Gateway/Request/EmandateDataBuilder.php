<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Emandate as EmandateConfig;

class EmandateDataBuilder extends AbstractDataBuilder
{

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Emandate
     */
    protected $config;

    public function __construct(EmandateConfig $config) {
        $this->config = $config;
    }
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        
        return [
            'emandatereason'    => (string)$this->config->getReason(),
            'sequencetype'      => (float)$this->config->getSequenceType(),
            'purchaseid'        => $this->getOrder()->getIncrementId(),
            'debtorbankid'      => (string)$this->getPayment()->getAdditionalInformation('issuer'),
            'debtorreference'   => $this->getEmail(),
            'language'          => (string)$this->config->getLanguage(),
        ];
    }

    /**
     * Get email from billingAddress
     * 
     * @return string
     */
    protected function getEmail(): string
    {
        $billingAddress = $this->getOrder()->getBillingAddress();
        if ($billingAddress !== null) {
            return (string)$billingAddress->getEmail();
        }
    }
}
