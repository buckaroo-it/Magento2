<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;

class TransferOrderDataBuilder extends AbstractDataBuilder
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
        parent::initialize($buildSubject);
        return [
            'dateDue' => $this->transferConfig->getDueDateFormated($this->getOrder()->getStore()),
            'sendMail' => (bool)$this->transferConfig->getOrderEmail($this->getOrder()->getStore()),
        ];
    }
}
