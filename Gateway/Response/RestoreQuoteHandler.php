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
use Magento\Checkout\Model\Session as CheckoutSession;
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);

        $orderId = $paymentDO->getOrder()->getId();

        $this->checkoutSession->setRestoreQuoteLastOrder($orderId);
    }
}
