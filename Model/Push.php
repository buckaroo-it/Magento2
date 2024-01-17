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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\PushInterface;
use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Push\PushProcessorsFactory;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;

class Push implements PushInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public BuckarooLoggerInterface $logger;

    /**
     * @var PushRequestInterface
     */
    public PushRequestInterface $pushRequst;

    /**
     * @var PushProcessorsFactory
     */
    private PushProcessorsFactory $pushProcessorsFactory;

    /**
     * @var OrderRequestService
     */
    private OrderRequestService $orderRequestService;

    /**
     * @var PushTransactionType
     */
    private PushTransactionType $pushTransactionType;

    /**
     * @var LockManagerWrapper
     */
    protected LockManagerWrapper $lockManager;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param RequestPushFactory $requestPushFactory
     * @param PushProcessorsFactory $pushProcessorsFactory
     * @param OrderRequestService $orderRequestService
     * @param PushTransactionType $pushTransactionType
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        RequestPushFactory $requestPushFactory,
        PushProcessorsFactory $pushProcessorsFactory,
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        LockManagerWrapper $lockManager
    ) {
        $this->logger = $logger;
        $this->pushRequst = $requestPushFactory->create();
        $this->pushProcessorsFactory = $pushProcessorsFactory;
        $this->orderRequestService = $orderRequestService;
        $this->pushTransactionType = $pushTransactionType;
        $this->lockManager = $lockManager;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     * @throws BuckarooException
     */
    public function receivePush(): bool
    {
        // Log the push request
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Webapi] | [%s] - Original Request | originalRequest: %s',
            __METHOD__,
            var_export($this->pushRequst->getOriginalRequest(), true)
        ));

        // Load Order
        $order = $this->orderRequestService->getOrderByRequest($this->pushRequst);

        $orderIncrementID = $order->getIncrementId();
        $this->logger->addDebug(__METHOD__ . '|Lock Name| - ' . var_export($orderIncrementID, true));
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

        if (!$lockAcquired) {
            $this->logger->addDebug(__METHOD__ . '|lock not acquired|');
            throw new \Buckaroo\Magento2\Exception(
                __('Lock push not acquired')
            );
        }

        try {
            // Validate Signature
            $store = $order->getStore();
            $validSignature = $this->pushRequst->validate($store);

            if (!$validSignature) {
                $this->logger->addDebug('[PUSH] | [Webapi] | ['. __METHOD__ .':'. __LINE__ . '] - Invalid push signature');
                throw new BuckarooException(__('Signature from push is incorrect'));
            }

            // Get Push Transaction Type
            $pushTransactionType = $this->pushTransactionType->getPushTransactionType($this->pushRequst, $order);

            // Process Push
            $pushProcessor = $this->pushProcessorsFactory->get($pushTransactionType);
            return $pushProcessor->processPush($this->pushRequst);
        } catch (\Throwable $e) {
            $this->logger->addDebug(__METHOD__ . '|Exception|' . $e->getMessage());
            throw $e;
        } finally {
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logger->addDebug(__METHOD__ . '|Lock released|');
        }
    }
}
