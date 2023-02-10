<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\Response\HandlerInterface;

class ConsumerMessageHandler implements HandlerInterface
{
    /**
     * @var MessageManager
     */
    protected MessageManager $messageManager;

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
        $consumerMessageData = $response->get('ConsumerMessage');

        if (!empty($consumerMessageData) && $consumerMessageData['MustRead'] == 1) {
            $this->messageManager->addSuccessMessage(
                __($consumerMessageData['Title'])
            );
            $this->messageManager->addSuccessMessage(
                __($consumerMessageData['PlainText'])
            );
        }
    }
}
