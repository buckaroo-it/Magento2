<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Helper\Data;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RestoreQuoteHandler implements HandlerInterface
{
    /**
     * Checkout session object
     *
     * @var \Magento\Checkout\Model\Session\Proxy
     */
    protected $checkoutSession;

    /**
     * Constructor
     *
     * @param  \Magento\Checkout\Model\Session\Proxy $checkoutSession
     * @return void
     */
    public function __construct(\Magento\Checkout\Model\Session\Proxy $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        $order = $payment->getOrder();

        $this->checkoutSession->setRestoreQuoteLastOrder($order->getId());
    }
}
