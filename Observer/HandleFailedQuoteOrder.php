<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Session as BuckarooSession;
use Buckaroo\Magento2\Service\Order\CancelGuard;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Manager;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class HandleFailedQuoteOrder implements ObserverInterface
{
    /**
     * @var BuckarooSession
     */
    protected $buckarooSession;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /** Methods that record a failed authorization on the payment */
    private const AUTHORIZE_METHODS = [
        'buckaroo_magento2_afterpay',
        'buckaroo_magento2_afterpay2',
        'buckaroo_magento2_klarnakp',
        'buckaroo_magento2_klarna',
    ];

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var CancelGuard
     */
    private CancelGuard $cancelGuard;

    /**
     * @param BuckarooSession $buckarooSession
     * @param BuckarooLoggerInterface $logger
     * @param Manager $moduleManager
     * @param OrderManagementInterface $orderManagement
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param CancelGuard|null $cancelGuard
     */
    public function __construct(
        BuckarooSession $buckarooSession,
        BuckarooLoggerInterface $logger,
        Manager $moduleManager,
        OrderManagementInterface $orderManagement,
        OrderPaymentRepositoryInterface $paymentRepository,
        ?CancelGuard $cancelGuard = null
    ) {
        $this->buckarooSession = $buckarooSession;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->orderManagement = $orderManagement;
        $this->paymentRepository = $paymentRepository;
        $this->cancelGuard = $cancelGuard ?? ObjectManager::getInstance()->get(CancelGuard::class);
    }

    /**
     * Cancel the order of a failed quote-to-order conversion
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var $order Order */
        $order = $observer->getEvent()->getOrder();

        if (!$order || !$order->getId()) {
            $this->logger->addDebug(sprintf(
                '[CANCEL_ORDER] | [Observer] | [%s:%s] - Order has no ID (not persisted), skipping cancellation.',
                __METHOD__,
                __LINE__
            ));
            return;
        }

        if ($this->moduleManager->isEnabled('Magento_Inventory')) {
            $this->buckarooSession->setData('flagHandleFailedQuote', 1);
        }

        try {
            $this->cancelGuard->cancel(
                (int)$order->getId(),
                'failed quote-to-order',
                function (Order $freshOrder) {
                    $this->flagFailedAuthorization($freshOrder);
                }
            );
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[CANCEL_ORDER] | [Observer] | [%s:%s] - Error when canceling order | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        } finally {
            $this->buckarooSession->setData('flagHandleFailedQuote', 0);
        }
    }

    /**
     * Mark a failed authorization for the methods that track it, on the order read under the lock
     *
     * @param Order $order
     *
     * @return void
     */
    private function flagFailedAuthorization(Order $order): void
    {
        $payment = $order->getPayment();
        if (!in_array($payment->getMethod(), self::AUTHORIZE_METHODS, true)) {
            return;
        }

        try {
            $order->addCommentToStatusHistory('Buckaroo: failed to authorize an order');
            $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
            $this->paymentRepository->save($payment);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[CANCEL_ORDER] | [Observer] | [%s:%s] - Error when adding order comment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
        }
    }
}
