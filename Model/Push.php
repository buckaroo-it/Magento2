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

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Api\PushInterface;
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
    public $logger;

    /**
     * @var \Buckaroo\Magento2\Api\Data\PushRequestInterface
     */
    public $pushRequest;

    /**
     * @var PushProcessorsFactory
     */
    private $pushProcessorsFactory;

    /**
     * @var OrderRequestService
     */
    private $orderRequestService;

    /**
     * @var PushTransactionType
     */
    private $pushTransactionType;

    /**
     * @var LockManagerWrapper
     */
    protected $lockManager;

    /**
     * @param BuckarooLoggerInterface $logger
     * @param RequestPushFactory      $requestPushFactory
     * @param PushProcessorsFactory   $pushProcessorsFactory
     * @param OrderRequestService     $orderRequestService
     * @param PushTransactionType     $pushTransactionType
     * @param LockManagerWrapper      $lockManager
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
        $this->pushRequest = $requestPushFactory->create();
        $this->pushProcessorsFactory = $pushProcessorsFactory;
        $this->orderRequestService = $orderRequestService;
        $this->pushTransactionType = $pushTransactionType;
        $this->lockManager = $lockManager;
    }

    /**
     * @inheritdoc
     *
     * @throws BuckarooException|\Throwable
     *
     * @return bool
     */
    public function receivePush(): bool
    {
        // Load Order
        $order = $this->orderRequestService->getOrderByRequest($this->pushRequest);

        $orderIncrementID = $order->getIncrementId();
        $this->logger->addDebug(__METHOD__ . '|Lock Name| - ' . var_export($orderIncrementID, true));
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

        if (!$lockAcquired) {
            $this->logger->addDebug(__METHOD__ . '|lock not acquired|');
            throw new BuckarooException(__('Lock push not acquired'));
        }

        try {
            // Validate Signature
            $store = $order->getStore();
            $validSignature = $this->pushRequest->validate($store);

            if (!$validSignature) {
                $this->logger->addDebug('
                    [PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Invalid push signature');
                throw new BuckarooException(__('Signature from push is incorrect'));
            }

            // Get Push Transaction Type
            $pushTransactionType = $this->pushTransactionType->getPushTransactionType($this->pushRequest, $order);

            // Process Push
            $pushProcessor = $this->pushProcessorsFactory->get($pushTransactionType);
            return $pushProcessor->processPush($this->pushRequest);
        } catch (\Throwable $e) {
            $this->logger->addDebug(__METHOD__ . '|Exception|' . $e->getMessage());
            throw $e;
        } finally {
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logger->addDebug(__METHOD__ . '|Lock released|');
        }
    }
}
