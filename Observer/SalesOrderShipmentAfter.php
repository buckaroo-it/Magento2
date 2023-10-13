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

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
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
    private Shipment $shipment;

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var OrderPaymentInterface|null
     */
    private ?OrderPaymentInterface $payment;

    /**
     * @var Data
     */
    public Data $helper;

    /**
     *
     * @var CollectionFactory
     */
    protected $invoiceCollectionFactory;

    /**
     * @var InvoiceService
     */
    protected InvoiceService $invoiceService;

    /**
     * @var ShipmentFactory
     */
    protected ShipmentFactory $shipmentFactory;

    /**
     * @var TransactionFactory
     */
    protected TransactionFactory $transactionFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var ConfigProviderFactory
     */
    private ConfigProviderFactory $configProviderFactory;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var PaymentGroupTransaction
     */
    private PaymentGroupTransaction $groupTransaction;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @param CollectionFactory $invoiceCollectionFactory
     * @param InvoiceService $invoiceService
     * @param ShipmentFactory $shipmentFactory
     * @param TransactionFactory $transactionFactory
     * @param ConfigProviderFactory $configProviderFactory
     * @param Data $helper
     * @param BuckarooLoggerInterface $logger
     * @param PaymentGroupTransaction $groupTransaction
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        CollectionFactory $invoiceCollectionFactory,
        InvoiceService $invoiceService,
        ShipmentFactory $shipmentFactory,
        TransactionFactory $transactionFactory,
        ConfigProviderFactory $configProviderFactory,
        Data $helper,
        BuckarooLoggerInterface $logger,
        PaymentGroupTransaction $groupTransaction,
        InvoiceSender $invoiceSender
    ) {
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->configProviderFactory = $configProviderFactory;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->groupTransaction = $groupTransaction;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * Create invoice after shipment on sales_order_shipment_save_after event
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $this->shipment = $observer->getEvent()->getShipment();

        $this->order = $this->shipment->getOrder();
        $this->payment = $this->order->getPayment();
        $paymentMethod = $this->payment->getMethodInstance();
        $paymentMethodCode = $paymentMethod->getCode();

        $klarnakpConfig = $this->configProviderFactory->get('klarnakp');
        if (($paymentMethodCode == 'buckaroo_magento2_klarnakp')
            && $klarnakpConfig->isInvoiceCreatedAfterShipment()
        ) {
            $this->createInvoice();
            return;
        }

        $afterpayConfig = $this->configProviderFactory->get('afterpay20');
        if (($paymentMethodCode == 'buckaroo_magento2_afterpay20')
            && $afterpayConfig->isInvoiceCreatedAfterShipment()
            && ($paymentMethod->getConfigPaymentAction() == 'authorize')
        ) {
            $this->createInvoice( true);
            return;
        }

        $this->configAccount = $this->configProviderFactory->get('account');
        if (strpos($paymentMethodCode, 'buckaroo_magento2') !== false
            && $this->configAccount->getInvoiceHandling() == InvoiceHandlingOptions::SHIPMENT) {
            $this->createInvoiceGeneralSetting();
        }
    }

    /**
     * Create invoice automatically after shipment
     *
     * @param bool $allowPartialsWithDiscount
     * @return InvoiceInterface|Invoice|null
     * @throws \Exception
     */
    private function createInvoice(bool $allowPartialsWithDiscount = false)
    {
        $this->logger->addDebug(sprintf(
            '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | orderDiscountAmount: %s',
            __METHOD__,
            __LINE__,
            var_export($this->order->getDiscountAmount(), true)
        ));

        try {
            if (!$this->order->canInvoice()) {
                return null;
            }

            if (!$allowPartialsWithDiscount && ($this->order->getDiscountAmount() < 0)) {
                $invoice = $this->invoiceService->prepareInvoice($this->order);
                $message = 'Automatically invoiced full order (can not invoice partials with discount)';
            } else {
                $qtys = $this->getQtys();
                $invoice = $this->invoiceService->prepareInvoice($this->order, $qtys);
                $message = 'Automatically invoiced shipped items.';
            }

            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $this->order->addStatusHistoryComment($message, false);
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

            if ($this->order->getStatus() == 'complete') {
                $description = 'Total amount of '
                    . $this->order->getBaseCurrency()->formatTxt($this->order->getTotalInvoiced())
                    . ' has been paid';
                $this->order->addStatusHistoryComment($description, false);
                $this->order->save();
            }
        } catch (\Exception $e) {
            $this->logger->addDebug(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
            $this->order->addStatusHistoryComment('Exception message: ' . $e->getMessage(), false);
            $this->order->save();
            return null;
        }

        return $invoice;
    }

    /**
     * Create invoice after shipment for all buckaroo payment methods
     *
     * @return bool
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createInvoiceGeneralSetting(): bool
    {
        $this->logger->addDebug('[CREATE_INVOICE] | [Observer] | ['. __METHOD__ .':'. __LINE__ . '] - Save Invoice');

        if (!$this->order->canInvoice() || $this->order->hasInvoices()) {
            $this->logger->addDebug(
                '[CREATE_INVOICE] | [Observer] | ['. __METHOD__ .':'. __LINE__ . '] - Order can not be invoiced'
            );

            return false;
        }

        //Fix for suspected fraud when the order currency does not match with the payment's currency
        $amount = ($this->payment->isSameCurrency()
            && $this->payment->isCaptureFinal($this->order->getGrandTotal())) ?
            $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
        $this->payment->registerCaptureNotification($amount);
        $this->payment->save();

        $transactionKey = (string)$this->payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if ($this->groupTransaction->isGroupTransaction($this->order->getIncrementId())) {
                $this->logger->addDebug(
                    '[CREATE_INVOICE] | [Observer] | [' . __METHOD__ . ':' . __LINE__ . '] - Set invoice state PAID group transaction'
                );
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logger->addDebug(
                    '[CREATE_INVOICE] | [Observer] | ['. __METHOD__ .':'. __LINE__ . '] - Send Invoice Email '
                );
                $this->invoiceSender->send($invoice, true);
            }
        }

        $this->order->setIsInProcess(true);
        $this->order->save();

        return true;
    }

    /**
     * Get shipped quantities
     *
     * @return array
     */
    public function getQtys(): array
    {
        $qtys = [];
        foreach ($this->shipment->getItems() as $items) {
            $qtys[$items->getOrderItemId()] = $items->getQty();
        }
        return $qtys;
    }
}
