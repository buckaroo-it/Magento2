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
use Buckaroo\Magento2\Service\Push\KlarnaMorDataRequestPushDetector;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Buckaroo\Magento2\Service\Store\StoreEmulator;

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
     * @var KlarnaMorDataRequestPushDetector
     */
    private KlarnaMorDataRequestPushDetector $klarnaMorDataRequestPushDetector;

    /**
     * @var StoreEmulator|null
     */
    private ?StoreEmulator $storeEmulator;

    /**
     * @param BuckarooLoggerInterface           $logger
     * @param RequestPushFactory                $requestPushFactory
     * @param PushProcessorsFactory             $pushProcessorsFactory
     * @param OrderRequestService               $orderRequestService
     * @param PushTransactionType               $pushTransactionType
     * @param LockManagerWrapper                $lockManager
     * @param KlarnaMorDataRequestPushDetector  $klarnaMorDataRequestPushDetector
     * @param StoreEmulator|null                $storeEmulator
     */
    public function __construct(
        BuckarooLoggerInterface $logger,
        RequestPushFactory $requestPushFactory,
        PushProcessorsFactory $pushProcessorsFactory,
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        LockManagerWrapper $lockManager,
        KlarnaMorDataRequestPushDetector $klarnaMorDataRequestPushDetector,
        ?StoreEmulator $storeEmulator = null
    ) {
        $this->logger = $logger;
        $this->pushRequest = $requestPushFactory->create();
        $this->pushProcessorsFactory = $pushProcessorsFactory;
        $this->orderRequestService = $orderRequestService;
        $this->pushTransactionType = $pushTransactionType;
        $this->lockManager = $lockManager;
        $this->klarnaMorDataRequestPushDetector = $klarnaMorDataRequestPushDetector;
        $this->storeEmulator = $storeEmulator;
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
        try {
            $order = $this->orderRequestService->getOrderByRequest($this->pushRequest);
        } catch (BuckarooException $e) {
            if (!empty($this->pushRequest->getRelatedtransactionPartialpayment())) {
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [%s:%s] - Failed pre-order partial payment push, no order found — acknowledging: %s',
                    __METHOD__,
                    __LINE__,
                    $e->getMessage()
                ));
                return true;
            }

            if ($this->klarnaMorDataRequestPushDetector->shouldAcknowledgeWithoutOrder($this->pushRequest)) {
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [KLARNA_MOR] | [%s:%s] - Plaza data request push without order reference'
                    . ' acknowledged | dataRequest: %s | message: %s',
                    __METHOD__,
                    __LINE__,
                    $this->pushRequest->getDatarequest() ?? 'null',
                    $e->getMessage()
                ));
                return true;
            }

            throw $e;
        }

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

            // Process Push in the order's store scope
            return $this->processPushInStoreScope($order);
        } catch (\Throwable $e) {
            $this->logger->addDebug(__METHOD__ . '|Exception|' . $e->getMessage());
            throw $e;
        } finally {
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logger->addDebug(__METHOD__ . '|Lock released|');
        }
    }

    /**
     * Run the push processor with the order's store emulated
     *
     * The push arrives on rest/V1/buckaroo/push, a REST route with no store code and no store
     * cookie, so Magento\Webapi\Controller\PathProcessor never calls setCurrentStore() and the
     * ambient store is the default store view of the default website. Everything the processor
     * chain reads without an explicit store — order statuses, invoice handling, order and invoice
     * emails, plus any third-party observer it triggers — would resolve against that store instead
     * of the one the order was placed in.
     *
     * This mirrors how Magento itself crosses the same boundary (Order\Email\Sender\*,
     * Order\Pdf\*, Payment\Helper\Data). It is a safety net, not a substitute for passing the
     * store to a known reader.
     *
     * Emulation deliberately starts only after the signature has been validated: the SDK verifies
     * the push against the URI built by UrlInterface::getDirectUrl(), and emulating a store with a
     * different base URL would change that URI and break validation.
     *
     * @param \Magento\Sales\Model\Order $order
     *
     * @throws \Throwable
     *
     * @return bool
     */
    private function processPushInStoreScope($order): bool
    {
        if ($this->storeEmulator === null) {
            return $this->runPushProcessor($order);
        }

        return $this->storeEmulator->emulate(
            $order->getStoreId(),
            function () use ($order) {
                return $this->runPushProcessor($order);
            }
        );
    }

    /**
     * Resolve the push transaction type and hand the push to its processor
     *
     * @param \Magento\Sales\Model\Order $order
     *
     * @throws \Throwable
     *
     * @return bool
     */
    private function runPushProcessor($order): bool
    {
        $pushTransactionType = $this->pushTransactionType->getPushTransactionType($this->pushRequest, $order);
        $pushProcessor = $this->pushProcessorsFactory->get($pushTransactionType);

        return $pushProcessor->processPush($this->pushRequest);
    }
}
