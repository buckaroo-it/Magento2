<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class DispatchEventHandler implements HandlerInterface
{
    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @var string
     */
    private string $command;

    /**
     * @param ManagerInterface $eventManager
     * @param string $command
     */
    public function __construct(ManagerInterface $eventManager, string $command)
    {
        $this->eventManager = $eventManager;
        $this->command = $command;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     * @throws \Exception
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        $this->eventManager->dispatch('buckaroo_' . $this->command . '_after', ['order' => $order]);
        $this->dispatchAfterEvent('buckaroo_magento2_method_' . $this->command . '_after', $payment, $response);
    }

    /**
     * Dispatch After Event
     *
     * @param $name
     * @param $payment
     * @param $response
     *
     * @return $this
     */
    protected function dispatchAfterEvent($name, $payment, $response)
    {
        $this->eventManager->dispatch(
            $name,
            [
                'payment' => $payment,
                'response' => $response,
            ]
        );

        return $this;
    }
}
