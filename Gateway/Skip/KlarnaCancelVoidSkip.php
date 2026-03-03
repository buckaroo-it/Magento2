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
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;

/**
 * Skip the CancelReservation command only when no DataRequest key exists.
 */
class KlarnaCancelVoidSkip implements SkipCommandInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Skip the CancelReservation command when there is no DataRequest key to cancel.
     *
     * @param array $commandSubject
     * @return bool
     */
    public function isSkip(array $commandSubject): bool
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment   = $paymentDO->getPayment();
        $order     = $payment->getOrder();

        $dataRequestKey = $order->getBuckarooDatarequestKey()
            ?? $payment->getAdditionalInformation('buckaroo_datarequest_key');

        if (empty($dataRequestKey)) {
            $this->logger->addDebug(sprintf(
                '[SKIP_CANCEL_RESERVATION - %s] | [KlarnaCancelVoidSkip] | [%s:%s] - '
                . 'Skipping CancelReservation: no DataRequest key found. Order: %s.',
                $payment->getMethod(),
                __METHOD__,
                __LINE__,
                $order ? $order->getIncrementId() : 'N/A'
            ));

            return true;
        }

        $this->logger->addDebug(sprintf(
            '[CANCEL_RESERVATION - %s] | [KlarnaCancelVoidSkip] | [%s:%s] - '
            . 'Proceeding with CancelReservation. DataRequest key: %s. Order: %s.',
            $payment->getMethod(),
            __METHOD__,
            __LINE__,
            $dataRequestKey,
            $order ? $order->getIncrementId() : 'N/A'
        ));

        return false;
    }
}
