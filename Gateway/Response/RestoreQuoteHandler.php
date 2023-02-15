<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Gateway\Response;

use Magento\Checkout\Model\Session as CheckoutSession;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RestoreQuoteHandler implements HandlerInterface
{
    /**
     * Checkout session object
     *
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * Constructor
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(CheckoutSession $checkoutSession)
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

        $orderId = $paymentDO->getOrder()->getId();

        $this->checkoutSession->setRestoreQuoteLastOrder($orderId);
    }
}
