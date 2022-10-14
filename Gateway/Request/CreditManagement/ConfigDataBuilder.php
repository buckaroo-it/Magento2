<?php

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;

class ConfigDataBuilder extends AbstractDataBuilder
{

    /** @var Factory */
    private $configProvider;

    public function __construct(Factory $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    public function build(array $buildSubject)
    {
        parent::initialize($buildSubject);

        $this->config = $this->configProvider->get(
            $this->getPayment()->getMethod()
        );

        $data = [
            'dueDate'         => $this->getDueDate(),
            'schemeKey'       => $this->configProvider->getSchemeKey(),
            'maxStepIndex'    => $this->configProvider->getMaxStepIndex(),
            'allowedServices' => $this->configProvider->getAllowedServices(),
        ];

        if ($this->config->getPaymentMethodAfterExpiry()) {
            $data['allowedServicesAfterDueDate'] = $this->configProvider->getPaymentMethodAfterExpiry();
        }
        return $data;

    }
    /**
     * Get transfer due date
     * @return string 
     */
    protected function getDueDate(): string
    {
        $dueDays = abs($this->config->getCm3DueDate());
        return (new \DateTime())
            ->modify("+{$dueDays} day")
            ->format('Y-m-d');
    }
}
