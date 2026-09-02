<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class DispatchEventHandler implements HandlerInterface
{
    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var string
     */
    private $command;

    /**
     * @param ManagerInterface $eventManager
     * @param string           $command
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
     *
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
     * @param string        $name
     * @param InfoInterface $payment
     * @param array         $response
     *
     * @return $this
     */
    protected function dispatchAfterEvent(string $name, InfoInterface $payment, array $response): DispatchEventHandler
    {
        $this->eventManager->dispatch(
            $name,
            [
                'payment'  => $payment,
                'response' => $response,
            ]
        );

        return $this;
    }
}
