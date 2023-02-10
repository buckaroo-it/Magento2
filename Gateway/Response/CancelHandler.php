<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CancelHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $payment->setAdditionalInformation('voided_by_buckaroo', true);
    }
}
