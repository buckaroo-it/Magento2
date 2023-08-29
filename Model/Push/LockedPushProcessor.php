<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\Data\TransactionInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LockedPushProcessor extends DefaultProcessor
{
    public const BUCK_PUSH_IDEAL_PAY = 'C021';
    protected const LOCK_PREFIX = 'bk_push_ideal_';

    /**
     * @var LockManagerInterface
     */
    protected LockManagerInterface $lockManager;

    /**
     * @param OrderRequestService $orderRequestService
     * @param PushTransactionType $pushTransactionType
     * @param BuckarooLoggerInterface $logger
     * @param Data $helper
     * @param TransactionInterface $transaction
     * @param PaymentGroupTransaction $groupTransaction
     * @param BuckarooStatusCode $buckarooStatusCode
     * @param OrderStatusFactory $orderStatusFactory
     * @param Account $configAccount
     * @param LockManagerInterface $lockManager
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        BuckarooLoggerInterface $logger,
        Data $helper,
        TransactionInterface $transaction,
        PaymentGroupTransaction $groupTransaction,
        BuckarooStatusCode $buckarooStatusCode,
        OrderStatusFactory $orderStatusFactory,
        Account $configAccount,
        LockManagerInterface $lockManager
    ) {
        parent::__construct($orderRequestService, $pushTransactionType, $logger, $helper, $transaction,
            $groupTransaction, $buckarooStatusCode, $orderStatusFactory, $configAccount);
        $this->lockManager = $lockManager;

    }

    /**
     * @throws FileSystemException
     * @throws BuckarooException
     */
    public function processPush(PushRequestInterface $pushRequest): bool
    {
        $this->pushRequest = $pushRequest;

        $lockName = $this->generateLockName();

        $this->stopLockedPush($lockName);

        if ($this->lockPushProcessingCriteria()) {
            $this->lockManager->lock($lockName);
        }

        try {
            parent::processPush($pushRequest);
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return true;
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

    /**
     * Generate a unique lock name for the push request.
     *
     * @return string
     */
    protected function generateLockName(): string
    {
        return self::LOCK_PREFIX . sha1($this->getOrderIncrementId());
    }

    /**
     * Ensure the push request is not currently locked.
     *
     * @param string $lockName
     * @return void
     * @throws BuckarooException
     */
    protected function stopLockedPush(string $lockName): void
    {
        if ($this->lockManager->isLocked($lockName)) {
            throw new BuckarooException(
                __('The Push for order %1 is currently being processed by another request. ' .
                    'Please wait a few moments and then try resending the request.', $this->getOrderIncrementId())
            );
        }
    }
}