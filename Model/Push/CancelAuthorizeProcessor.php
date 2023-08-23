<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;

class CancelAuthorizeProcessor extends DefaultProcessor
{
    /**
     * Handle cancelled order authorization and update payment transactions.
     *
     * @param PushRequestInterface $pushRequest
     * @return bool
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        try {
            $this->setTransactionKey();
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[PUSH_CANCEL_AUTHORIZE] | [Webapi] | [%s:%s] - cancelled order authorization | [ERROR]: %s',
                __METHOD__, __LINE__,
                $e->getLogMessage()
            ));
        }

        $this->logger->addDebug(sprintf(
            '[PUSH_CANCEL_AUTHORIZE] | [Webapi] | [%s:%s] - Order autorize has been canceld,' .
            ' trying to update payment transactions',
            __METHOD__, __LINE__,
        ));

        return true;
    }
}