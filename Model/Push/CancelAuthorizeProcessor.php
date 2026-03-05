<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;

class CancelAuthorizeProcessor extends DefaultProcessor
{
    /**
     * Handle canceled order authorization and update payment transactions.
     *
     * @param PushRequestInterface $pushRequest
     *
     * @return bool
     * @throws \Exception
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->initializeFields($pushRequest);

        $this->logger->addDebug(sprintf(
            '[PUSH_CANCEL_AUTHORIZE] | [Webapi] | [%s:%s] - Order authorize has been cancelled,' .
            ' trying to update payment transactions',
            __METHOD__,
            __LINE__,
        ));

        try {
            $this->setTransactionKey();
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[PUSH_CANCEL_AUTHORIZE] | [Webapi] | [%s:%s] - cancelled order authorization | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        }

        return true;
    }
}
