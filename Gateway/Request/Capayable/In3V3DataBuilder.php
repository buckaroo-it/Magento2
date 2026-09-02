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

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Payment\Gateway\Request\BuilderInterface;

class In3V3DataBuilder implements BuilderInterface
{
    /**
     * @var CapayableIn3
     */
    private $capayableIn3Config;

    /**
     * @param CapayableIn3 $capayableIn3Config
     */
    public function __construct(
        CapayableIn3 $capayableIn3Config
    ) {
        $this->capayableIn3Config = $capayableIn3Config;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $data = [];

        if (!$this->capayableIn3Config->isV2()) {
            $payment->setAdditionalInformation("buckaroo_in3_v3", true);
            $data['payment_method'] = 'in3';
        }

        return $data;
    }
}
