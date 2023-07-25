<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Service\LockerProcess;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

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

    protected function setBuckarooReservationNumber(): bool
    {
        if (!empty($this->pushRequest->getServiceKlarnaReservationnumber())) {
            $this->order->setBuckarooReservationNumber($this->pushRequest->getServiceKlarnaReservationnumber());
            $this->order->save();
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function skipKlarnaCapture(): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasPostData('transaction_method', ['klarnakp', 'KlarnaKp'])
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
            && !empty($this->pushRequest->getServiceKlarnakpCaptureid())
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $paymentDetails
     * @return bool
     * @throws \Exception
     */
    protected function invoiceShouldBeSaved(array &$paymentDetails): bool
    {
        if (!empty($this->pushRequest->getServiceKlarnakpAutopaytransactionkey())
            && ($this->pushRequest->getStatusCode() == 190)
        ) {
            return true;
        }

        return true;
    }
}