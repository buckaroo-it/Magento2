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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarna;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SalesOrderShipmentAfter implements ObserverInterface
{
    public const MODULE_ENABLED = 'sr_auto_invoice_shipment/settings/enabled';

    /**
     * @var Shipment
     */
    private $shipment;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var ConfigProviderFactory
     */
    private $configProviderFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var CreateInvoice
     */
    private $createInvoiceService;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @param InvoiceService               $invoiceService
     * @param TransactionFactory           $transactionFactory
     * @param ConfigProviderFactory        $configProviderFactory
     * @param BuckarooLoggerInterface      $logger
     * @param CreateInvoice                $createInvoiceService
     * @param OrderRepositoryInterface     $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     */
    public function __construct(
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        ConfigProviderFactory $configProviderFactory,
        BuckarooLoggerInterface $logger,
        CreateInvoice $createInvoiceService,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository
    ) {
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->configProviderFactory = $configProviderFactory;
        $this->logger = $logger;
        $this->createInvoiceService = $createInvoiceService;
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Create an invoice after shipment on sales_order_shipment_save_after event
     *
     * @param Observer $observer
     *
     * @throws LocalizedException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute(Observer $observer)
    {
        $this->shipment = $observer->getEvent()->getShipment();

        $this->order = $this->orderRepository->get($this->shipment->getOrderId());
        /** @var Order\Payment $payment */
        $payment = $this->order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        $paymentMethodCode = $paymentMethod->getCode();
        $storeId = (int)$this->order->getStoreId();

        /** @var Klarnakp $klarnakpConfig */
        $klarnakpConfig = $this->configProviderFactory->get('klarnakp');
        if (($paymentMethodCode == 'buckaroo_magento2_klarnakp')
            && $klarnakpConfig->isInvoiceCreatedAfterShipment($storeId)
        ) {
            $this->createInvoice();
            $this->syncBundleTogetherChildQtyShipped();
            return;
        }

        /** @var Klarna $klarnaConfig */
        $klarnaConfig = $this->configProviderFactory->get('klarna');
        if (($paymentMethodCode == 'buckaroo_magento2_klarna')
            && $klarnaConfig->isInvoiceCreatedAfterShipment($storeId)
        ) {
            $this->createInvoice();
            $this->syncBundleTogetherChildQtyShipped();
            return;
        }

        /** @var Afterpay20 $afterpayConfig */
        $afterpayConfig = $this->configProviderFactory->get('afterpay20');
        if (($paymentMethodCode == 'buckaroo_magento2_afterpay20')
            && $afterpayConfig->isInvoiceCreatedAfterShipment($storeId)
            && ($paymentMethod->getConfigPaymentAction() == 'authorize')
        ) {
            $this->createInvoice();
            $this->syncBundleTogetherChildQtyShipped();
            return;
        }

        if (strpos($paymentMethodCode, 'buckaroo_magento2') !== false
            && $this->isInvoiceCreatedAfterShipment($payment)) {
            if ($paymentMethod->getConfigPaymentAction() == 'authorize') {
                $this->createInvoice();
            } elseif (!$this->order->hasInvoices()) {
                $this->createInvoiceService->createInvoiceGeneralSetting($this->order, $this->getQtys());
            }

            $this->syncBundleTogetherChildQtyShipped();
        }
    }

    /**
     * Create invoice automatically after shipment
     *
     * Always invoices the shipped lines only. Invoicing the whole order on a discounted order
     * used to be the default, and captured the full authorization on a partial shipment
     * the discount is spread over the invoiced lines by the article handler.
     *
     * @throws \Exception
     *
     * @return InvoiceInterface|Invoice|null
     */
    private function createInvoice()
    {
        $this->logger->addDebug(sprintf(
            '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | orderDiscountAmount: %s',
            __METHOD__,
            __LINE__,
            var_export($this->order->getDiscountAmount(), true)
        ));

        $invoice = null;
        $mayHaveRegisteredItems = false;

        try {
            if (!$this->order->canInvoice()) {
                $this->logger->addDebug(sprintf(
                    '[CREATE_INVOICE] | [Observer] | [%s:%s] - Skip invoice creation: nothing left to invoice',
                    __METHOD__,
                    __LINE__
                ));
                return null;
            }

            $invoice = $this->invoiceService->prepareInvoice($this->order, $this->getQtys());

            if (!$invoice->getTotalQty()) {
                $this->logger->addDebug(sprintf(
                    '[CREATE_INVOICE] | [Observer] | [%s:%s] - Skip invoice creation: the shipped '
                    . 'items are already invoiced',
                    __METHOD__,
                    __LINE__
                ));
                return null;
            }

            $message = 'Automatically invoiced shipped items.';

            if ($this->isAlreadyPaidFor($invoice)) {
                $this->logger->addDebug(sprintf(
                    '[CREATE_INVOICE] | [Observer] | [%s:%s] - Using OFFLINE capture: this amount '
                    . 'was already credited to the order',
                    __METHOD__,
                    __LINE__
                ));
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            } else {
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            }

            // Invoice::register() writes the invoiced quantities onto the order items BEFORE it
            // attempts the capture, so from here on a failure has to be rolled back - flagging
            // this after register() returns leaves those values behind when the capture throws.
            $mayHaveRegisteredItems = true;
            $invoice->register();

            $invoice->getOrder()->setCustomerNoteNotify(0);
            $invoice->getOrder()->setIsInProcess(true);
            $this->order->addCommentToStatusHistory($message);

            if ($this->order->getStatus() == 'complete') {
                $description = 'Total amount of '
                    . $this->order->getBaseCurrency()->formatTxt($this->order->getBaseTotalInvoiced())
                    . ' has been paid';
                $this->order->addCommentToStatusHistory($description);
            }

            $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();

            $this->logger->addDebug(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | orderStatus: %s',
                __METHOD__,
                __LINE__,
                var_export($this->order->getStatus(), true)
            ));
        } catch (\Exception $e) {
            $this->handleInvoiceFailure($e, $mayHaveRegisteredItems ? $invoice : null);

            return null;
        }

        return $invoice;
    }

    /**
     * Whether the money this invoice covers has already reached the order.
     *
     * `buckaroo_already_captured` only says SOME capture happened and is never cleared, so on an
     * order that captures per shipment it would mark every later invoice paid with no gateway
     * call.
     *
     * @param Invoice $invoice
     *
     * @return bool
     */
    private function isAlreadyPaidFor(Invoice $invoice): bool
    {
        /** @var Order\Payment $payment */
        $payment = $this->order->getPayment();

        if (!$payment->getAdditionalInformation('buckaroo_already_captured')) {
            return false;
        }

        $paidButNotInvoiced = round(
            (float)$this->order->getTotalPaid() - (float)$this->order->getTotalInvoiced(),
            2
        );

        return $paidButNotInvoiced >= round((float)$invoice->getGrandTotal(), 2);
    }

    /**
     * Leave the order clean and the failure visible when the capture did not go through.
     *
     * The shipment itself is already committed, so without the comment the order looks
     * shipped-and-paid. Any invoiced values Invoice::register() wrote onto the order items are
     * reversed first, otherwise a later save in this request persists them and the order ends up
     * reporting invoiced items with no invoice entity (BTI-1312).
     *
     * @param \Exception   $exception
     * @param Invoice|null $registeredInvoice Null when register() was never reached.
     *
     * @return void
     */
    private function handleInvoiceFailure(\Exception $exception, ?Invoice $registeredInvoice): void
    {
        $this->logger->addError(sprintf(
            '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | [ERROR]: %s',
            __METHOD__,
            __LINE__,
            $exception->getMessage()
        ));

        if ($registeredInvoice !== null) {
            $this->rollBackRegisteredInvoiceValues($registeredInvoice);
        }

        $this->order->addCommentToStatusHistory(
            __('Buckaroo: automatic invoice creation after shipment FAILED: %1', $exception->getMessage())
        );

        $this->orderRepository->save($this->order);
    }

    /**
     * Reverse the invoiced values that Invoice::register() wrote onto the order items.
     *
     * Safe to call after a register() that threw part-way: Invoice::getAllItems() skips the
     * item register() marked deleted, so only quantities it actually added are subtracted.
     *
     * @param Invoice $invoice
     *
     * @return void
     */
    private function rollBackRegisteredInvoiceValues(Invoice $invoice): void
    {
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $invoiceItem->cancel();
        }
    }

    /**
     * Get the invoice qtys for the shipped items.
     *
     * Empty when the shipment completes the order, which makes the invoice
     * cover every remaining invoiceable item.
     *
     * @return array
     */
    public function getQtys(): array
    {
        return $this->createInvoiceService->getQtysFromShipment($this->shipment);
    }

    /**
     * For "Ship Together" bundles, Magento only records qty_shipped on the parent shipment item,
     * leaving children at qty_shipped=0. Magento's canShip() uses getSimpleQtyToShip() for bundle
     * children (bypasses isDummy), so children with qty_shipped=0 keep canShip() returning true
     * forever, preventing the order from reaching "complete" state.
     *
     * This method mirrors the parent's shipped quantity onto each child item so that
     * getSimpleQtyToShip() returns 0 for them, allowing canShip() to return false after all
     * items are invoiced, which lets Magento's state machine transition the order to "complete".
     */
    private function syncBundleTogetherChildQtyShipped(): void
    {
        $hasChanges = false;

        foreach ($this->order->getAllItems() as $item) {
            if ($item->getProductType() !== 'bundle' || $item->isShipSeparately()) {
                continue;
            }

            $parentQtyShipped = (float)$item->getQtyShipped();
            if ($parentQtyShipped <= 0) {
                continue;
            }

            foreach ($item->getChildrenItems() as $child) {
                if ((float)$child->getQtyShipped() >= (float)$child->getQtyOrdered()) {
                    continue;
                }
                $child->setQtyShipped($child->getQtyOrdered());
                try {
                    $this->orderItemRepository->save($child);
                    $hasChanges = true;
                } catch (\Exception $e) {
                    $this->logger->addDebug(sprintf(
                        '[SYNC_BUNDLE_QTY] | [Observer] | [%s:%s] - Failed to save child item %s: %s',
                        __METHOD__,
                        __LINE__,
                        $child->getId(),
                        $e->getMessage()
                    ));
                }
            }
        }

        if ($hasChanges) {
            $this->order->setIsInProcess(true);
            $this->orderRepository->save($this->order);
        }
    }

    /**
     * If the invoice for the current order is created after shipment
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     */
    private function isInvoiceCreatedAfterShipment(OrderPaymentInterface $payment): bool
    {
        /** @var Order\Payment $payment */
        $invoiceHandling = $payment->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        );

        if ($invoiceHandling == InvoiceHandlingOptions::SHIPMENT) {
            return true;
        }

        // Flag may be missing on authorize transactions placed before it was persisted;
        // fall back to the account Invoice Handling configuration.
        if ($invoiceHandling !== null && $invoiceHandling !== '') {
            return false;
        }

        try {
            $accountConfig = $this->configProviderFactory->get('account');
            return $accountConfig->getInvoiceHandling($this->order->getStore())
                == InvoiceHandlingOptions::SHIPMENT;
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Unable to resolve invoice handling config | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));

            return false;
        }
    }
}
