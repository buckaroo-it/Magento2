<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class ConsumerMessageHandler implements HandlerInterface
{

    public const INVOICE_KEY = 'buckaroo_cm3_invoice_key';

    /**
     * @var TransactionResponse
     */
    protected TransactionResponse $response;

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->validate($handlingSubject, $response);

        $this->response = $response['object'];

        $consumerMessage = $this->response->getConsumerMessage();

        if (!empty($consumerMessage)) {
            $handlingSubject['payment']->getPayment()->messageManager->addSuccessMessage(__($consumerMessage));
        }
    }

    /**
     * Validate data from request
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     * @throws  \InvalidArgumentException
     */
    protected function validate(array $handlingSubject, array $response)
    {
        $this->validatePayment($handlingSubject);
        $this->validateResponse($response);
    }

    private function validatePayment(array $handlingSubject)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
    }

    private function validateResponse(array $response)
    {
        if (!isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }
    }
}
