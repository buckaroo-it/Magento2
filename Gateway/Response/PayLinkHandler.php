<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PayLinkHandler extends AbstractResponseHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $response = SubjectReader::readTransactionResponse($response);
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
