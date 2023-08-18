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
            $this->logger->addDebug($e->getLogMessage());
        }

        $this->logger->addDebug('Order autorize has been canceld, trying to update payment transactions');

        return true;
    }
}