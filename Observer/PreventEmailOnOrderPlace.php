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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class PreventEmailOnOrderPlace implements ObserverInterface
{
    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(BuckarooLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Prevent email from being sent immediately for Buckaroo redirect payments
     * Email will be sent after successful payment confirmation via push
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment) {
            return;
        }

        // Check if it's a Buckaroo payment
        if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
            return;
        }

        try {
            // For ALL Buckaroo payments, prevent immediate email

            $order->setCanSendNewEmailFlag(false);

            $this->logger->addDebug(sprintf(
                '[PREVENT_EMAIL] | [Observer] | [%s:%s] - Prevented immediate email for Buckaroo payment | order: %s | method: %s | state: %s',
                __METHOD__,
                __LINE__,
                $order->getIncrementId(),
                $payment->getMethod(),
                $order->getState()
            ));
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[PREVENT_EMAIL] | [Observer] | [%s:%s] - Error: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        }
    }
}
