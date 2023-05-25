<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\LockerProcess;
use Magento\Framework\Exception\FileSystemException;

class KlarnaProcessor extends DefaultProcessor
{
    /**
     * Retrieves the transaction key from the push request.
     *
     * @return string
     */
    protected function getTransactionKey(): string
    {
        $trxId = parent::getTransactionKey();

        if (!empty($this->pushRequest->getServiceKlarnaAutopaytransactionkey())
        ) {
            $trxId = $this->pushRequest->getServiceKlarnaAutopaytransactionkey();
        }

        return $trxId;
    }
}