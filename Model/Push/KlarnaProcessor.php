<?php

namespace Buckaroo\Magento2\Model\Push;

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
