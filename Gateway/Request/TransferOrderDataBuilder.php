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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TransferOrderDataBuilder implements BuilderInterface
{
    /**
     * @var Transfer
     */
    protected $transferConfig;

    /**
     * @param Transfer $transferConfig
     */
    public function __construct(Transfer $transferConfig)
    {
        $this->transferConfig = $transferConfig;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        return [
            'dateDue'  => $this->transferConfig->getDueDateFormated($order->getStore()),
            'sendMail' => $this->transferConfig->hasOrderEmail($order->getStore()),
        ];
    }
}
