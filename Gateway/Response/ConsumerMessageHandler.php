<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;

class ConsumerMessageHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->validate($handlingSubject, $response);

        /** @var TransactionResponse $response */
        $response = $response['object'];
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
