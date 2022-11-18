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
            'schemeKey'       => $this->config->getSchemeKey(),
            'maxStepIndex'    => $this->config->getMaxStepIndex(),
        ];

        if ($this->config->getPaymentMethodAfterExpiry() != null) {
            $data['allowedServicesAfterDueDate'] = $this->getPaymentMethodsAfterExpiry();
        }
        return $data;
    }

    protected function getPaymentMethodsAfterExpiry(): string
    {
        $methods = $this->config->getPaymentMethodsAfterExpiry();
        if (is_array($methods)) {
            return implode(',', $methods);
        }
        return "";
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
