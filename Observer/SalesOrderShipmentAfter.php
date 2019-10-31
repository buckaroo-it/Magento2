<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace TIG\Buckaroo\Observer;

use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order\Invoice;
use \TIG\Buckaroo\Model\ConfigProvider\Account;
use \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Framework\DB\TransactionFactory;

class SalesOrderShipmentAfter implements ObserverInterface
{

    const MODULE_ENABLED = 'sr_auto_invoice_shipment/settings/enabled';

    /** @var TIG\Buckaroo\Model\ConfigProvider\Method\Klarna */
    private $klarnaConfig;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     *
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $invoiceCollectionFactory;

    /**
     *
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     *
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $shipmentFactory;

    /**
     *
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna $klarnaConfig
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna $klarnaConfig
    ) {
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->klarnaConfig = $klarnaConfig;
    }


    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ( !$this->klarnaConfig->getEnabled() ||
             !$this->klarnaConfig->getCreateInvoiceAfterShipment()
        ) {
            return;
        }

        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $shipment->getOrder();

        $payment = $order->getPayment();
        if ($payment->getMethodInstance()->getCode() == 'tig_buckaroo_klarna') {
            $this->createInvoice($order, $shipment);
        }
    }

    public function createInvoice($order, $shipment)
    {
        try {
            if(!$order->canInvoice()) {
                return null;
            }

            if ($order->getDiscountAmount() < 0) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $message = 'Automatically invoiced full order with discount. (Klarna can not invoice partials with discount)';
            }
            else {
                $qtys = $this->getQtys($shipment);
                $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
                $message = 'Automatically invoiced shipped items.';
            }


            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment($message, false);
            $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $order->save();
            return null;
        }

        return $invoice;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getQtys($shipment)
    {
        $qtys = [];
        foreach ($shipment->getItems() as $items) {
            $qtys[$items->getOrderItemId()] = $items->getQty();
        }
        return $qtys;
    }
}