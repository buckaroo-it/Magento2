<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Framework\Event\ManagerInterface;

class PayLinkHandler extends AbstractResponseHandler implements HandlerInterface
{
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
                    'Your PayLink <a href="%1">%1</a>',
                    $paylink
                )
            );
        } else {
            $this->messageManager->addErrorMessage('Error creating PayLink');
        }
    }
}
