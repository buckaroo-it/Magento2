<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CaptureHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @var bool
     */
    public bool $closeAuthorizeTransaction = false;

    /**
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $this->transactionResponse = SubjectReader::readTransactionResponse($response);
        $arrayResponse = $this->transactionResponse->toArray();

        $this->saveTransactionData($this->transactionResponse, $payment, $this->closeAuthorizeTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->registry->unregister('buckaroo_response');
        $this->registry->register('buckaroo_response', [0 => $arrayResponse]);

        $this->afterCapture($payment, $response);
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array|\StdCLass $response
     *
     * @return $this
     */
    protected function afterCapture($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_capture_after', $payment, $response);
    }
}
