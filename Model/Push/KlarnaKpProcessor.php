<?php

namespace Buckaroo\Magento2\Model\Push;

class KlarnaKpProcessor extends DefaultProcessor
{
    /**
     * Retrieves the transaction key from the push request.
     *
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $trxId = parent::getTransactionKey();

        if (!empty($this->pushRequest->getServiceKlarnakpAutopaytransactionkey())
        ) {
            $trxId = $this->pushRequest->getServiceKlarnakpAutopaytransactionkey();
        }

        return $trxId;
    }
}