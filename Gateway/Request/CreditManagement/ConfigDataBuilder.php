<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;

class ConfigDataBuilder extends AbstractDataBuilder
{
    /**
     * @var Factory
     */
    private $configProvider;

    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @param Factory $configProvider
     */
    public function __construct(Factory $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $this->config = $this->configProvider->get(
            $this->getPayment()->getMethod()
        );

        $data = [
            'dueDate'      => $this->getDueDate(),
            'schemeKey'    => $this->config->getSchemeKey(),
            'maxStepIndex' => $this->config->getMaxStepIndex(),
        ];

        if ($this->config->getPaymentMethodAfterExpiry() != null) {
            $data['allowedServicesAfterDueDate'] = $this->getPaymentMethodsAfterExpiry();
        }
        return $data;
    }

    /**
     * Get payment methods
     *
     * @return string
     */
    protected function getPaymentMethodsAfterExpiry(): string
    {
        $methods = $this->config->getPaymentMethodAfterExpiry();
        if (is_array($methods)) {
            return implode(',', $methods);
        }
        return "";
    }

    /**
     * Get transfer due date
     *
     * @return string
     */
    protected function getDueDate(): string
    {
        $dueDays = abs((float)$this->config->getCm3DueDate());
        return (new \DateTime())
            ->modify("+{$dueDays} day")
            ->format('Y-m-d');
    }
}
