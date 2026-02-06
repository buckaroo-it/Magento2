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

namespace Buckaroo\Magento2\Gateway\Skip;

use Buckaroo\Magento2\Gateway\Command\SkipCommandInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Response\TransactionIdHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

/**
 * Skip the cancel / void command if there's no valid transaction to cancel
 *
 * This prevents attempting to void/cancel payments at the gateway when:
 * - The initial transaction failed or was rejected
 * - No transaction key was ever set (payment never successfully processed)
 * - The order is being canceled due to configuration issues or gateway rejection
 */
class CancelVoidSkip implements SkipCommandInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooLoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Check if the cancel / void command should be skipped
     *
     * @param array $commandSubject
     * @return bool
     */
    public function isSkip(array $commandSubject): bool
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        // Check if there's a transaction key
        $transactionKey = $payment->getAdditionalInformation(
            TransactionIdHandler::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        // Skip if no transaction key exists
        if (empty($transactionKey)) {
            $this->logger->addDebug(sprintf(
                '[SKIP_VOID - %s] | [CancelVoidSkip] | [%s:%s] - Skipping cancel/void command: '
                . 'No transaction key found. Order: %s.',
                $payment->getMethod(),
                __METHOD__,
                __LINE__,
                $order ? $order->getIncrementId() : 'N/A'
            ));

            return true;
        }

        if ($order && $order->getState() === Order::STATE_NEW) {
            $this->logger->addDebug(sprintf(
                '[SKIP_VOID - %s] | [CancelVoidSkip] | [%s:%s] - Skipping cancel/void command: '
                . 'Order in NEW state (payment failed). Order: %s.',
                $payment->getMethod(),
                __METHOD__,
                __LINE__,
                $order->getIncrementId()
            ));

            return true;
        }

        // Skip if this is a failed authorization that cannot be voided at the gateway
        // This is common for Klarna/Afterpay orders where authorization failed after initial success
        if ($this->isFailedAuthorization($payment, $order)) {
            $this->logger->addDebug(sprintf(
                '[SKIP_VOID - %s] | [CancelVoidSkip] | [%s:%s] - Skipping cancel/void command: '
                . 'Failed authorization detected. Order: %s. Payment failed after authorization, '
                . 'no valid transaction to void at gateway.',
                $payment->getMethod(),
                __METHOD__,
                __LINE__,
                $order ? $order->getIncrementId() : 'N/A'
            ));

            return true;
        }

        return false;
    }

    /**
     * Check if a payment has a failed authorization flag
     *
     * This indicates the payment was initially authorized but then failed
     * (e.g., fraud check, risk assessment, or gateway rejection after initial success)
     *
     * @param InfoInterface $payment
     * @param Order|null $order
     * @return bool
     */
    private function isFailedAuthorization($payment, $order): bool
    {
        // Check for explicit failed authorization flag
        // This flag is set by DefaultProcessor::processFailedPush for Klarna/Afterpay methods
        $failedAuthorize = $payment->getAdditionalInformation('buckaroo_failed_authorize');
        if ($failedAuthorize == 1) {
            return true;
        }

        // For orders in the pending_payment state after having a transaction key,
        // this typically indicates authorization failed after initial success
        if ($order && $order->getState() === Order::STATE_PENDING_PAYMENT) {
            return true;
        }

        return false;
    }

}
