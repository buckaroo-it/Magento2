<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Framework\Event\ManagerInterface;

class OrderHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @var bool
     */
    public bool $closeOrderTransaction = true;

    /**
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (
            !isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        if (
            !isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }

        $this->transactionResponse = $response['object'];

        $payment = $handlingSubject['payment']->getPayment();
        $arrayResponse = $this->transactionResponse->toArray();

        $this->saveTransactionData($this->transactionResponse, $payment, $this->closeOrderTransaction, true);

        // SET REGISTRY BUCKAROO REDIRECT
        $this->registry->unregister('buckaroo_response');
        $this->registry->register('buckaroo_response', [0 => $arrayResponse]);

        if (!$this->transactionResponse->hasRedirect()) {
            $this->setPaymentInTransit($payment, false);
        }

        $order = $payment->getOrder();
        $this->helper->setRestoreQuoteLastOrder($order->getId());

        $this->eventManager->dispatch('buckaroo_order_after', ['order' => $order]);

        $this->afterOrder($payment, $arrayResponse);
    }

    /**
     * @param array|\StdCLass|TransactionResponse $response
     *
     * @return $this
     */
    protected function afterOrder($payment, $response)
    {
        return $this->dispatchAfterEvent('buckaroo_magento2_method_order_after', $payment, $response);
    }
}
