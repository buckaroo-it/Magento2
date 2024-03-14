<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ServiceVersionDataBuilder implements BuilderInterface
{
    public const BUCKAROO_SERVICE_VERSION_KEY = 'buckaroo_service_version';

    /**
     * @var int
     */
    private int $defaultServiceVersion;

    /**
     * @param int $defaultServiceVersion
     */
    public function __construct(int $defaultServiceVersion = 1)
    {
        $this->defaultServiceVersion = $defaultServiceVersion;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();;

        $serviceVersion = $payment->getAdditionalInformation(self::BUCKAROO_SERVICE_VERSION_KEY);
        if (empty($serviceVersion)) {
            $paymentInstance = $payment->getMethodInstance();
            if ($paymentInstance->getCode() == Afterpay20::CODE
                && !$paymentInstance->getConfigData(Afterpay20::XPATH_AFTERPAY20_SCA)) {
                return [];
            }
            $serviceVersion = $this->defaultServiceVersion;
            $payment->setAdditionalInformation(self::BUCKAROO_SERVICE_VERSION_KEY, $serviceVersion);
        }

        return ['serviceVersion' => $serviceVersion];
    }
}
