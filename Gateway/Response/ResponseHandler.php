<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class ResponseHandler extends AbstractMethod implements HandlerInterface
{

    public function getOrderTransactionBuilder($payment)
    {
        // TODO: Implement getOrderTransactionBuilder() method.
    }

    public function getAuthorizeTransactionBuilder($payment)
    {
        // TODO: Implement getAuthorizeTransactionBuilder() method.
    }

    public function getVoidTransactionBuilder($payment)
    {
        // TODO: Implement getVoidTransactionBuilder() method.
    }

    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        if (!isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }

        $payment = $handlingSubject['payment']->getPayment();
        $responseData = json_decode(json_encode($response['object']->toArray()));

        $this->saveTransactionData($responseData, $payment, $this->closeOrderTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', [0 => $response['object']->toArray()]);

        if (!$response['object']->hasRedirect()) {
            $this->setPaymentInTransit($response['object']->getTransactionKey(), false);
        }

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

        $this->eventManager->dispatch('buckaroo_order_after', ['order' => $order]);

        $this->afterOrder($payment, $response);
    }
}
