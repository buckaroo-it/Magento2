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
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
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
     * @var Data
     */
    public $helper;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     *
     * @var CollectionFactory
     */
    protected $invoiceCollectionFactory;
    /**
     *
     * @var InvoiceService
     */
    protected $invoiceService;
    /**
     *
     * @var ShipmentFactory
     */
    protected $shipmentFactory;
    /**
     *
     * @var TransactionFactory
     */
    protected $transactionFactory;
    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;
    /**
     * @var Klarnakp
     */
    private $klarnakpConfig;
    /**
     * @var Afterpay20
     */
    private $afterpayConfig;

    /**
     * @param CollectionFactory $invoiceCollectionFactory
     * @param InvoiceService $invoiceService
     * @param ShipmentFactory $shipmentFactory
     * @param TransactionFactory $transactionFactory
     * @param Klarnakp $klarnakpConfig
     * @param Afterpay20 $afterpayConfig
     * @param Data $helper
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $invoiceCollectionFactory,
        InvoiceService $invoiceService,
        ShipmentFactory $shipmentFactory,
        TransactionFactory $transactionFactory,
        Klarnakp $klarnakpConfig,
        Afterpay20 $afterpayConfig,
        Data $helper,
        BuckarooLoggerInterface $logger
    ) {
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->klarnakpConfig = $klarnakpConfig;
        $this->afterpayConfig = $afterpayConfig;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Create invoice after shipment on sales_order_shipment_save_after event
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        $order = $shipment->getOrder();
        $payment = $order->getPayment();

        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_klarnakp')
            && $this->klarnakpConfig->isInvoiceCreatedAfterShipment()
        ) {
            $this->createInvoice($order, $shipment);
        }

        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_afterpay20')
            && $this->afterpayConfig->isInvoiceCreatedAfterShipment()
            && ($payment->getMethodInstance()->getConfigPaymentAction() == 'authorize')
        ) {
            $this->createInvoice($order, $shipment, true);
        }
    }

    /**
     * Create invoice automatically after shipment
     *
     * @param Order $order
     * @param Shipment $shipment
     * @param bool $allowPartialsWithDiscount
     * @return InvoiceInterface|Invoice|null
     * @throws \Exception
     */
    private function createInvoice(Order $order, Shipment $shipment, bool $allowPartialsWithDiscount = false)
    {
        $this->logger->addDebug(sprintf(
            '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | orderDiscountAmount: %s',
            __METHOD__,
            __LINE__,
            var_export($order->getDiscountAmount(), true)
        ));

        try {
            if (!$order->canInvoice()) {
                return null;
            }

            if (!$allowPartialsWithDiscount && ($order->getDiscountAmount() < 0)) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $message = 'Automatically invoiced full order (can not invoice partials with discount)';
            } else {
                $qtys = $this->getQtys($shipment);
                $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
                $message = 'Automatically invoiced shipped items.';
            }

            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment($message, false);
            $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();

            $this->logger->addDebug(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | orderStatus: %s',
                __METHOD__,
                __LINE__,
                var_export($order->getStatus(), true)
            ));

            if ($order->getStatus() == 'complete') {
                $description = 'Total amount of '
                    . $order->getBaseCurrency()->formatTxt($order->getTotalInvoiced())
                    . ' has been paid';
                $order->addStatusHistoryComment($description, false);
                $order->save();
            }
        } catch (\Exception $e) {
            $this->logger->addDebug(sprintf(
                '[CREATE_INVOICE] | [Observer] | [%s:%s] - Create invoice after shipment | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
            $order->addStatusHistoryComment('Exception message: ' . $e->getMessage(), false);
            $order->save();
            return null;
        }

        return $invoice;
    }

    /**
     * Get shipped quantities
     *
     * @param Shipment $shipment
     * @return array
     */
    public function getQtys(Shipment $shipment): array
    {
        $qtys = [];
        foreach ($shipment->getItems() as $items) {
            $qtys[$items->getOrderItemId()] = $items->getQty();
        }
        return $qtys;
    }
}
