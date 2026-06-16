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
     * @param InvoiceService          $invoiceService
     * @param TransactionFactory      $transactionFactory
     * @param ConfigProviderFactory   $configProviderFactory
     * @param BuckarooLoggerInterface $logger
     * @param CreateInvoice           $createInvoiceService
     */
    public function __construct(
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        ConfigProviderFactory $configProviderFactory,
        BuckarooLoggerInterface $logger,
        CreateInvoice $createInvoiceService
    ) {
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->configProviderFactory = $configProviderFactory;
        $this->logger = $logger;
        $this->createInvoiceService = $createInvoiceService;
    }

    /**
     * Create invoice after shipment on sales_order_shipment_save_after event
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

        $this->order = $this->shipment->getOrder();
        /** @var Order\Payment $payment */
        $payment = $this->order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        $paymentMethodCode = $paymentMethod->getCode();

        /** @var Klarnakp $klarnakpConfig */
        $klarnakpConfig = $this->configProviderFactory->get('klarnakp');
        if (($paymentMethodCode == 'buckaroo_magento2_klarnakp')
            && $klarnakpConfig->isInvoiceCreatedAfterShipment()
        ) {
            if (!$this->order->hasInvoices()) {
                $this->createInvoice();
            }
            return;
        }

        /** @var Klarna $klarnaConfig */
        $klarnaConfig = $this->configProviderFactory->get('klarna');
        if (($paymentMethodCode == 'buckaroo_magento2_klarna')
            && $klarnaConfig->isInvoiceCreatedAfterShipment()
        ) {
            if (!$this->order->hasInvoices()) {
                $this->createInvoice(true);
            }
            return;
        }

        /** @var Afterpay20 $afterpayConfig */
        $afterpayConfig = $this->configProviderFactory->get('afterpay20');
        if (($paymentMethodCode == 'buckaroo_magento2_afterpay20')
            && $afterpayConfig->isInvoiceCreatedAfterShipment()
            && ($paymentMethod->getConfigPaymentAction() == 'authorize')
        ) {
            if (!$this->order->hasInvoices()) {
                $this->createInvoice(true);
            }
            return;
        }

        if (strpos($paymentMethodCode, 'buckaroo_magento2') !== false
            && $this->isInvoiceCreatedAfterShipment($payment)) {
            if ($this->order->hasInvoices()) {
                return;
            }

            if ($paymentMethod->getConfigPaymentAction() == 'authorize') {
                $this->createInvoice(true);
            } else {
                $this->createInvoiceService->createInvoiceGeneralSetting($this->order, $this->getQtys());
            }
        }
    }

    /**
     * Create invoice automatically after shipment
     *
     * @param bool $allowPartialsWithDiscount
     *
     * @throws \Exception
     *
     * @return InvoiceInterface|Invoice|null
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
            if ($this->order->hasInvoices()) {
                $this->logger->addDebug(sprintf(
                    '[CREATE_INVOICE] | [Observer] | [%s:%s] - Skip invoice creation: Invoice already exists',
                    __METHOD__,
                    __LINE__
                ));
                return null;
            }

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

            // Check if payment was already captured (e.g., during order reactivation)
            /** @var Order\Payment $payment */
            $payment = $this->order->getPayment();
            $wasCaptured = $payment->getAdditionalInformation('buckaroo_already_captured');

            if ($wasCaptured) {
                // Payment already captured, use offline capture to avoid duplicate
                $this->logger->addDebug(sprintf(
                    '[CREATE_INVOICE] | [Observer] | [%s:%s] - Using OFFLINE capture: payment already captured during reactivation',
                    __METHOD__,
                    __LINE__
                ));
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);

            } else {
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            }

            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(0);
            $invoice->getOrder()->setIsInProcess(true);
            $this->order->addCommentToStatusHistory($message);

            if ($this->order->getStatus() == 'complete') {
                $description = 'Total amount of '
                    . $this->order->getBaseCurrency()->formatTxt($this->order->getTotalInvoiced())
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
            $this->logger->addDebug(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
            return null;
        }

        return $invoice;
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
     * If the invoice for the current order is created after shipment
     *
     * @param OrderPaymentInterface $payment
     *
     * @return bool
     */
    private function isInvoiceCreatedAfterShipment(OrderPaymentInterface $payment): bool
    {
        /** @var Order\Payment $payment */
        return $payment->getAdditionalInformation(
            InvoiceHandlingOptions::INVOICE_HANDLING
        ) == InvoiceHandlingOptions::SHIPMENT;
    }
}
