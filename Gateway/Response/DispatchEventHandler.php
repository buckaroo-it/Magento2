<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
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
     * @param  array      $handlingSubject
     * @param  array      $response
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
