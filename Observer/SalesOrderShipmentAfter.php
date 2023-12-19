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
use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;

class SalesOrderShipmentAfter implements ObserverInterface
{
    const MODULE_ENABLED = 'sr_auto_invoice_shipment/settings/enabled';

    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var InvoiceService
     */
    protected InvoiceService $invoiceService;

    /**
     * @var TransactionFactory
     */
    protected TransactionFactory $transactionFactory;

    /**
     * @var GatewayInterface
     */
    protected GatewayInterface $gateway;

    /**
     * @var Log
     */
    protected Log $logger;

    /**
     * @var Klarnakp
     */
    private Klarnakp $klarnakpConfig;

    /**
     * @var Afterpay20
     */
    private Afterpay20 $afterpayConfig;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var CreateInvoice
     */
    private CreateInvoice $createInvoiceService;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param Klarnakp $klarnakpConfig
     * @param Afterpay20 $afterpayConfig
     * @param GatewayInterface $gateway
     * @param Data $helper
     * @param Account $configAccount
     * @param CreateInvoice $createInvoiceService
     * @param Log $logger
     */
    public function __construct(
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        Klarnakp $klarnakpConfig,
        Afterpay20 $afterpayConfig,
        GatewayInterface $gateway,
        Data $helper,
        Account $configAccount,
        CreateInvoice $createInvoiceService,
        Log $logger,
        RequestInterface $request
    ) {
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->klarnakpConfig = $klarnakpConfig;
        $this->afterpayConfig = $afterpayConfig;
        $this->configAccount = $configAccount;
        $this->createInvoiceService = $createInvoiceService;
        $this->helper = $helper;
        $this->gateway = $gateway;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * @throws Exception
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $shipment->getOrder();

        $invoiceData = $this->request->getParam('shipment', []);
        $invoiceItems = isset($invoiceData['items']) ? $invoiceData['items'] : [];

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
                $this->createInvoiceService->createInvoiceGeneralSetting($order, $invoiceItems);
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
            $order->addStatusHistoryComment('Exception message: ' . $e->getMessage(), false);
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
