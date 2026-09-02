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
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class PayLinkHandler implements HandlerInterface
{
    /**
     * @var MessageManager
     */
    protected $messageManager;

    /**
     * @param MessageManager $messageManager
     */
    public function __construct(
        MessageManager $messageManager
    ) {
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $response = SubjectReader::readTransactionResponse($response);
        $paylink = $response->getServiceParameters()['paylink'] ?? '';
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var Order $order */
        $order = $paymentDO->getOrder()->getOrder();

        if (!empty($paylink)) {
            $order->addCommentToStatusHistory('Paylink: ' . $paylink);
            $this->messageManager->addSuccess(
                __(
                    'Your PayLink <a href="%1">%1</a>',
                    $paylink
                )
            );
        } else {
            $this->messageManager->addErrorMessage('Error creating PayLink');
        }
    }
}
