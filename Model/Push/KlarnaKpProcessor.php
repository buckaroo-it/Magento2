<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Api\Data\TransactionInterface;

class KlarnaKpProcessor extends DefaultProcessor
{
    /**
     * @var Klarnakp
     */
    private Klarnakp $klarnakpConfig;

    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        Log $logging,
        Data $helper,
        TransactionInterface $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode $buckarooStatusCode,
        OrderStatusFactory $orderStatusFactory,
        Account $configAccount,
        Klarnakp $klarnakpConfig
    ) {
        parent::__construct($orderRequestService, $pushTransactionType, $logging, $helper, $transaction,
            $groupTransaction, $buckarooStatusCode, $orderStatusFactory, $configAccount);
        $this->klarnakpConfig = $klarnakpConfig;
    }

    /**
     * Skip the push if the conditions are met.
     *
     * @return bool
     * @throws \Exception
     */
    protected function skipPush(): bool
    {
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1)
            && $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay')
            && !empty($this->pushRequest->getServiceKlarnakpCaptureid())
        ) {
            return true;
        }

        return parent::skipPush();
    }

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

    protected function setBuckarooReservationNumber(): bool
    {
        if (!empty($this->pushRequest->getServiceKlarnakpReservationnumber())) {
            $this->order->setBuckarooReservationNumber($this->pushRequest->getServiceKlarnakpReservationnumber());
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
        if ($this->pushRequest->hasAdditionalInformation('initiated_by_magento', 1) &&
            (
                $this->pushRequest->hasPostData('transaction_method', 'KlarnaKp') &&
                $this->pushRequest->hasAdditionalInformation('service_action_from_magento', 'pay') &&
                empty($this->pushRequest->getServiceKlarnakpReservationnumber()) &&
                $this->klarnakpConfig->isInvoiceCreatedAfterShipment()
            )) {
            $this->logging->addDebug(__METHOD__ . '|5_1|');
            $this->dontSaveOrderUponSuccessPush = true;
            return false;
        }

        if (!empty($this->pushRequest->getServiceKlarnakpAutopaytransactionkey())
            && ($this->pushRequest->getStatusCode() == 190)
        ) {
            return true;
        }

        return true;
    }
}