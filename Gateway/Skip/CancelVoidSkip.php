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
use Magento\Sales\Model\Order;

/**
 * Skip cancel/void command if there's no valid transaction to cancel
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
     * Check if cancel/void command should be skipped
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

        return false;
    }

}
