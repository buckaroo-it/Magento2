<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Model\Method\AbstractMethod;
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

        $payment = $handlingSubject['payment']->getPayment();

        $this->saveTransactionData($response[0], $payment, $this->closeOrderTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->_registry->unregister('buckaroo_response');
        $this->_registry->register('buckaroo_response', $response);

        if (!(isset($response->RequiredAction->Type) && $response->RequiredAction->Type === 'Redirect')) {
            $this->setPaymentInTransit($payment, false);
        }

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());
    }
}
