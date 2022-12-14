<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TransferOrderDataBuilder implements BuilderInterface
{
    /**
     * @var Transfer
     */
    protected Transfer $transferConfig;

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
            'dateDue' => $this->transferConfig->getDueDateFormated($order->getStore()),
            'sendMail' => (bool)$this->transferConfig->getOrderEmail($order->getStore()),
        ];
    }
}
