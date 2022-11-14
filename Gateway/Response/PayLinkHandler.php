<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Framework\Event\ManagerInterface;

class PayLinkHandler implements HandlerInterface
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * Constructor
     *
     * @param ManagerInterface $messageManager
     */
    public function __construct(\Magento\Framework\Message\ManagerInterface $messageManager)
    {
        $this->messageManager = $messageManager;
    }

    /**
     * @inheritdoc
     */
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

        $response = $response['object'];

        if (isset($response->getServiceParameters()['paylink'])) {
          $payLink = $response->getServiceParameters()['paylink'];
        }

        if (empty($payLink)) {
            $this->messageManager->addErrorMessage('Error creating PayLink');
        } else {
            $this->messageManager->addSuccess(
                __(
                    'You PayLink <a href="%1">%1</a>',
                    $payLink
                )
            );
        }
    }
}
