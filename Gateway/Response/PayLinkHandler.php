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
        $this->validate($handlingSubject, $response);

        $response = $response['object'];
        $paylink = $response->getServiceParameters()['paylink'] ?? '';

        if (!empty($paylink)) {
            $this->messageManager->addSuccess(
                __(
                    'You PayLink <a href="%1">%1</a>',
                    $paylink
                )
            );
        } else {
            $this->messageManager->addErrorMessage('Error creating PayLink');
        }
    }

    /**
     * Validate data from request
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate(array $handlingSubject, array $response)
    {
        $this->validatePayment($handlingSubject);
        $this->validateResponse($response);
    }

    private function validatePayment(array $handlingSubject)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
    }

    private function validateResponse(array $response)
    {
        if (!isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }
    }

}
