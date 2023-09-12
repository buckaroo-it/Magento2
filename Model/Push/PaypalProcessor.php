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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal as PaypalConfig;
use Buckaroo\Magento2\Model\OrderStatusFactory;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;

class PaypalProcessor extends DefaultProcessor
{
    private PaypalConfig $paypalConfig;

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
     * @param PaypalConfig $paypalConfig
     *
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
        PaypalConfig $paypalConfig
    ) {
        parent::__construct(
            $orderRequestService,
            $pushTransactionType,
            $logger,
            $helper,
            $transaction,
            $groupTransaction,
            $buckarooStatusCode,
            $orderStatusFactory,
            $configAccount
        );
        $this->paypalConfig = $paypalConfig;
    }

    /**
     * @return false|string|null
     * @throws BuckarooException
     * @throws LocalizedException
     */
    protected function getNewStatus()
    {
        $newStatus = $this->orderStatusFactory->get($this->pushRequest->getStatusCode(), $this->order);

        if ($this->pushTransactionType->getStatusKey() == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $this->order->getPayment()->getMethod() == PaypalConfig::CODE) {
            $newSellersProtectionStatus = $this->paypalConfig->getSellersProtectionIneligible();
            if ($this->paypalConfig->getSellersProtection() && !empty($newSellersProtectionStatus)) {
                $newStatus = $newSellersProtectionStatus;
            }
        }

        $this->logger->addDebug(sprintf(
            '[PUSH - PayPerEmail] | [Webapi] | [%s:%s] - Get New Status | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        return $newStatus;
    }
}
