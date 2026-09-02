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

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AfterpaySCADataBuilder implements BuilderInterface
{
    public const BUCKAROO_SERVICE_VERSION_KEY = 'buckaroo_service_version';

    /**
     * @var Afterpay20
     */
    private $configAfterpay;

    /**
     * @param Afterpay20 $configAfterpay
     */
    public function __construct(Afterpay20 $configAfterpay)
    {
        $this->configAfterpay = $configAfterpay;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $serviceVersion = $payment->getAdditionalInformation(self::BUCKAROO_SERVICE_VERSION_KEY);

        if (!empty($serviceVersion)) {
            return ['serviceVersion' => $serviceVersion];
        }

        if ($this->configAfterpay->isEnabledSCA($paymentDO->getOrder()->getStoreId())) {
            $payment->setAdditionalInformation(self::BUCKAROO_SERVICE_VERSION_KEY, 2);
            return ['serviceVersion' => 2];
        }

        return [];
    }
}
