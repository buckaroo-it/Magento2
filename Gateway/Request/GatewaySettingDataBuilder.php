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

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderMethodFactory;
use Magento\Payment\Gateway\Request\BuilderInterface;

class GatewaySettingDataBuilder implements BuilderInterface
{
    /**
     * @var ConfigProviderMethodFactory
     */
    protected $configProviderMethodFactory;

    /**
     * Constructor
     *
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Factory $configProviderMethodFactory
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $method = $paymentDO->getPayment()->getMethodInstance()->getCode();
        if (!$method) {
            throw new Exception(
                __("The payment method code it is not set.")
            );
        }
        $configProvider = $this->configProviderMethodFactory->get($method);

        if (method_exists($configProvider, 'getGatewaySettings')) {
            $paymentMethod = [
                'payment_method' => $configProvider->getGatewaySettings(),
            ];
        }

        return $paymentMethod ?? [];
    }
}
