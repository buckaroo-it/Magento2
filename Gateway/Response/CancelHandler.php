<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CancelHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @var bool
     */
    public $closeCancelTransaction = true;

    /**
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $this->transactionResponse = SubjectReader::readTransactionResponse($response);
        $arrayResponse = $this->transactionResponse->toArray();

        $this->saveTransactionData($this->transactionResponse, $payment, $this->closeCancelTransaction, true);

        $payment->setAdditionalInformation('voided_by_buckaroo', true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->addToRegistry('buckaroo_response', $arrayResponse);

        $this->afterVoid($payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass $response
     *
     * @return $this
     */
    protected function afterVoid($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_void_after', $payment, $response);
    }
}
