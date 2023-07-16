<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\LockerProcess;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\FileSystemException;
use Magento\Sales\Api\Data\TransactionInterface;

class IdealProcessor extends DefaultProcessor
{
    public const BUCK_PUSH_IDEAL_PAY = 'C021';

    /**
     * @var LockerProcess
     */
    private LockerProcess $lockerProcess;

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
        LockerProcess $lockerProcess,
    ) {
        parent::__construct($orderRequestService, $pushTransactionType, $logging, $helper, $transaction,
            $groupTransaction, $buckarooStatusCode,$orderStatusFactory, $configAccount);
        $this->lockerProcess = $lockerProcess;

    }

    /**
     * @throws FileSystemException
     * @throws BuckarooException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        // Lock Processing
        if ($this->lockPushProcessingCriteria()) {
            $this->lockerProcess->lockProcess($this->getOrderIncrementId());
        }

        parent::processPush($pushRequest);

        $this->lockerProcess->unlockProcess();
    }

    /**
     * Determine if the lock push processing criteria are met.
     *
     * @return bool
     */
    protected function lockPushProcessingCriteria(): bool
    {
        return $this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            && $this->pushRequest->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY);
    }

    public function processSucceded()
    {
        $statusCodeSuccess = BuckarooStatusCode::SUCCESS;

        if ($this->pushRequest->hasPostData('statuscode', BuckarooStatusCode::SUCCESS)
            && $this->pushRequest->hasPostData('transaction_method', 'ideal')
            && $this->pushRequest->hasPostData('transaction_type', self::BUCK_PUSH_IDEAL_PAY)
        ) {
            return true;
        }
    }

    public function processFailed()
    {
        // TODO: Implement processFailed() method.
    }


}