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
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Push\PushProcessorsFactory;
use Buckaroo\Magento2\Model\Push\PushTransactionType;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Push implements PushInterface
{
    /**
     * @var Log $logging
     */
    public Log $logging;

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
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * @param Log $logging
     * @param RequestPushFactory $requestPushFactory
     * @param PushProcessorsFactory $pushProcessorsFactory
     * @param OrderRequestService $orderRequestService
     * @param PushTransactionType $pushTransactionType
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Log $logging,
        RequestPushFactory $requestPushFactory,
        PushProcessorsFactory $pushProcessorsFactory,
        OrderRequestService $orderRequestService,
        PushTransactionType $pushTransactionType,
        ResultFactory $resultFactory
    ) {
        $this->logging = $logging;
        $this->pushRequst = $requestPushFactory->create();
        $this->pushProcessorsFactory = $pushProcessorsFactory;
        $this->orderRequestService = $orderRequestService;
        $this->pushTransactionType = $pushTransactionType;
        $this->resultFactory = $resultFactory;
    }

    /**
     * @inheritdoc
     *
     * @return ResultInterface
     * @throws BuckarooException
     */
    public function receivePush(): ResultInterface
    {
        try {
            // Log the push request
            $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->pushRequst->getOriginalRequest(), true));

            // Load Order
            $order = $this->orderRequestService->getOrderByRequest($this->pushRequst);

            // Validate Signature
            $store = $order->getStore();
            $validSignature = $this->pushRequst->validate($store);

            if (!$validSignature) {
                $this->logging->addDebug('Invalid push signature');
                throw new BuckarooException(__('Signature from push is incorrect'));
            }

            // Get Push Transaction Type
            $pushTransactionType = $this->pushTransactionType->getPushTransactionType($this->pushRequst, $order);

            // Process Push
            $pushProcessor = $this->pushProcessorsFactory->get($pushTransactionType);

            $responseContent = [
                'success'       => $pushProcessor->processPush($this->pushRequst),
                'error_message' => ''
            ];
        } catch (\Throwable $exception) {
            $responseContent = [
                'success'       => false,
                'error_message' => $exception->getMessage()
            ];
            $this->logging->addError(__METHOD__ . '|2|' . $exception->getMessage());
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($responseContent);
        $resultJson->setHttpResponseCode(200);
        return $resultJson;
    }
}
