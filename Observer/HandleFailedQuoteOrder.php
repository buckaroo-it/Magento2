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
use Buckaroo\Magento2\Model\Session as BuckarooSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;

class HandleFailedQuoteOrder implements ObserverInterface
{
    /**
     * @var BuckarooSession
     */
    protected $buckarooSession;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @param BuckarooSession $buckarooSession
     * @param BuckarooLoggerInterface $logger
     * @param Manager $moduleManager
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        BuckarooSession $buckarooSession,
        BuckarooLoggerInterface $logger,
        Manager $moduleManager,
        OrderManagementInterface $orderManagement
    ) {
        $this->buckarooSession = $buckarooSession;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Observes the event for a failed quote-to-order submission
     * Handle cancel order by sales_model_service_quote_submit_failure event
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();

        if ($order->canCancel()) {
            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/BuckarooAdapter.php:880
            $payment = $order->getPayment();
            if (in_array(
                $payment->getMethodInstance()->getCode(),
                ['buckaroo_magento2_afterpay', 'buckaroo_magento2_afterpay2', 'buckaroo_magento2_klarnakp']
            )) {
                try {
                    $order->addCommentToStatusHistory('Buckaroo: failed to authorize an order');
                    $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                    $payment->save();
                } catch (\Exception $e) {
                    $this->logger->addError(sprintf(
                        '[CANCEL_ORDER] | [Observer] | [%s:%s] - Error when adding order comment | [ERROR]: %s',
                        __METHOD__,
                        __LINE__,
                        $e->getMessage()
                    ));
                }
            }

            try {
                $this->logger->addDebug(sprintf(
                    '[CANCEL_ORDER] | [Observer] | [%s:%s] - Cancel order for failed quote-to-order | order: %s',
                    __METHOD__,
                    __LINE__,
                    $order->getId()
                ));
                if ($this->moduleManager->isEnabled('Magento_Inventory')) {
                    $this->buckarooSession->setData('flagHandleFailedQuote', 1);
                }
                $this->orderManagement->cancel($order->getId());
            } catch (\Exception $e) {
                $this->logger->addError(sprintf(
                    '[CANCEL_ORDER] | [Observer] | [%s:%s] - Error when canceling order | [ERROR]: %s',
                    __METHOD__,
                    __LINE__,
                    $e->getMessage()
                ));
            }
            $this->buckarooSession->setData('flagHandleFailedQuote', 0);
        }
    }
}
