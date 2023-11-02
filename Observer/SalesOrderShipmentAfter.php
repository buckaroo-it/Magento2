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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;

class SalesOrderShipmentAfter implements ObserverInterface
{

    const MODULE_ENABLED = 'sr_auto_invoice_shipment/settings/enabled';

    /** @var Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp */
    private $klarnakpConfig;

    private $afterpayConfig;

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
     * @var \Buckaroo\Magento2\Gateway\GatewayInterface
     */
    protected $gateway;

    /**
     * @var \Buckaroo\Magento2\Helper\Data
     */
    public $helper;

    protected $logger;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var CreateInvoice
     */
    private CreateInvoice $createInvoice;

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp $klarnakpConfig
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp $klarnakpConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20 $afterpayConfig,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway,
        \Buckaroo\Magento2\Helper\Data $helper,
        Account $configAccount,
        CreateInvoice $createInvoice,
        Log $logger
    ) {
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->klarnakpConfig = $klarnakpConfig;
        $this->afterpayConfig = $afterpayConfig;
        $this->configAccount = $configAccount;
        $this->createInvoice = $createInvoice;
        $this->helper = $helper;
        $this->gateway = $gateway;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $shipment->getOrder();

        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethodInstance();
        $paymentMethodCode = $paymentMethod->getCode();

        $this->logger->addDebug(__METHOD__ . '|1|');

        if (($paymentMethodCode == 'buckaroo_magento2_klarnakp')
            && $this->klarnakpConfig->getCreateInvoiceAfterShipment()
        ) {
            $this->gateway->setMode(
                $this->helper->getMode('buckaroo_magento2_klarnakp')
            );
            $this->createInvoice($order, $shipment);
        }

        if (($paymentMethodCode == 'buckaroo_magento2_afterpay20')
            && $this->afterpayConfig->getCreateInvoiceAfterShipment()
            && ($paymentMethod->getConfigPaymentAction() == 'authorize')
        ) {
            $this->gateway->setMode(
                $this->helper->getMode('buckaroo_magento2_afterpay20')
            );
            $this->createInvoice($order, $shipment, true);
        }

        if (strpos($paymentMethodCode, 'buckaroo_magento2') !== false
            && $this->configAccount->getInvoiceHandling() == InvoiceHandlingOptions::SHIPMENT) {
            if ($paymentMethod->getConfigPaymentAction() == 'authorize') {
                $this->createInvoice($order, $shipment, true);
            } else {
                $this->createInvoice->createInvoiceGeneralSetting($order);
            }
        }
    }


    private function createInvoice($order, $shipment, $allowPartialsWithDiscount = false)
    {
        $this->logger->addDebug(__METHOD__ . '|1|' . var_export($order->getDiscountAmount(), true));

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

            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment($message, false);
            $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();

            $this->logger->addDebug(__METHOD__ . '|3|' . var_export($order->getStatus(), true));

            if ($order->getStatus() == 'complete') {
                $description = 'Total amount of '
                    . $order->getBaseCurrency()->formatTxt($order->getTotalInvoiced())
                    . ' has been paid';
                $order->addStatusHistoryComment($description, false);
                $order->save();
            }

            $this->logger->addDebug(__METHOD__ . '|4|');
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
