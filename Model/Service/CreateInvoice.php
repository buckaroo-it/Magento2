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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Shipment;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateInvoice
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var Account
     */
    private $configAccount;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private $orderStatusHistoryRepository;

    /**
     * @param Account                 $configAccount
     * @param Log                     $logger
     * @param PaymentGroupTransaction $groupTransaction
     * @param InvoiceSender           $invoiceSender
     * @param InvoiceService          $invoiceService
     * @param TransactionFactory      $transactionFactory
     * @param Registry                $registry
     * @param Data                    $helper
     * @param Json|null               $jsonSerializer
     * @param OrderStatusHistoryRepositoryInterface|null $orderStatusHistoryRepository
     */
    public function __construct(
        Account $configAccount,
        Log $logger,
        PaymentGroupTransaction $groupTransaction,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        Registry $registry,
        Data $helper,
        Json $jsonSerializer = null,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository = null
    ) {
        $this->logger = $logger;
        $this->groupTransaction = $groupTransaction;
        $this->invoiceSender = $invoiceSender;
        $this->configAccount = $configAccount;
        $this->invoiceService = $invoiceService;
        $this->helper = $helper;
        $this->transactionFactory = $transactionFactory;
        $this->registry = $registry;
        $this->jsonSerializer = $jsonSerializer
            ?? \Magento\Framework\App\ObjectManager::getInstance()->get(Json::class);
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository
            ?? \Magento\Framework\App\ObjectManager::getInstance()->get(OrderStatusHistoryRepositoryInterface::class);
    }

    /**
     * Create invoice after shipment for all buckaroo payment methods
     *
     * @param Order $order
     * @param array $invoiceItems
     *
     * @throws LocalizedException
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function createInvoiceGeneralSetting(Order $order, array $invoiceItems): bool
    {
        if (!$order->canInvoice()) {
            return true;
        }

        $invoiceItems = $this->prepareInvoiceItems($order, $invoiceItems);

        $invoice = $this->invoiceService->prepareInvoice($order, $invoiceItems);

        if (!$invoice->getTotalQty()) {
            // Throwing here would roll back the shipment save that triggered this observer,
            // making the order impossible to ship at all. Skip the invoice instead.
            $this->logger->addDebug(
                __METHOD__ . ' - Skip invoice creation: no invoiceable items for the current shipment'
            );
            $this->orderStatusHistoryRepository->save(
                $order->addCommentToStatusHistory(
                    (string)__('Skipped automatic invoice on shipment: the shipment contains no invoiceable items.')
                )
            );
            return false;
        }

        $this->registry->register('current_invoice', $invoice);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);

        $invoice->register();

        $invoice->getOrder()->setCustomerNoteNotify(0);
        $invoice->getOrder()->setIsInProcess(true);

        $transactionSave = $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transactionSave->save();

        /** @var Order\Payment $payment */
        $payment = $invoice->getOrder()->getPayment();

        $transactionKey = (string)$payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        /** @var Invoice $invoice */
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if ($this->groupTransaction->isGroupTransaction($order->getIncrementId())) {
                $this->logger->addDebug(__METHOD__ . '|3| - Set invoice state PAID group transaction');
                $invoice->setState(Invoice::STATE_PAID);
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($order->getStoreId())) {
                $this->logger->addDebug(__METHOD__ . '|4| - Send Invoice Email');
                $this->invoiceSender->send($invoice, true);
            }
        }

        return true;
    }

    /**
     * Get Order Items that are not invoiced
     *
     * @param Order $order
     *
     * @return array
     */
    public function getInvoiceItems(Order $order): array
    {
        $invoiceItems = [];

        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                $invoiceItems[$item->getItemId()] = $item->getQtyToInvoice();
            }
        }

        return $invoiceItems;
    }

    /**
     * @param mixed $payment
     * @param mixed $transactionKey
     * @param mixed $datas
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return Order\Payment
     */
    public function addTransactionData($payment, $transactionKey = false, $datas = false)
    {
        $transactionKey = $transactionKey ?: $payment->getAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );

        if (strlen($transactionKey) <= 0) {
            throw new \Buckaroo\Magento2\Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        if (!$datas) {
            $rawDetails = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
            $rawInfo = $rawDetails[$transactionKey] ?? [];
        } else {
            $rawInfo = $this->helper->getTransactionAdditionalInfo($datas);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $rawInfo);

        /**
         * Save the payment's transaction key.
         */
        $captureTransactionKey = $payment->getAdditionalInformation('buckaroo_capture_transaction_key');
        $payment->setTransactionId($captureTransactionKey ?: $transactionKey . '-capture');
        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            BuckarooAdapter::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }

    /**
     * Build the invoice qtys for the items included in a shipment.
     *
     * Returns an empty array when the shipment completes the shipping of the order, so the
     * caller invoices every remaining invoiceable item, including virtual items that can
     * never be part of a shipment.
     *
     * @param Shipment $shipment
     *
     * @return array order_item_id => qty
     */
    public function getQtysFromShipment(Shipment $shipment): array
    {
        $order = $shipment->getOrder();

        if (!$order->canShip()) {
            return [];
        }

        $qtys = [];
        foreach ($shipment->getAllItems() as $shipmentItem) {
            $qtys[(int)$shipmentItem->getOrderItemId()] = (float)$shipmentItem->getQty();
        }

        return $this->correctBundleParentQtys($order, $qtys);
    }

    /**
     * Correct the qty of bundle parents that are shipped separately.
     *
     * Magento stores a hardcoded qty of 1 on the dummy parent shipment item of a
     * "Ship Separately" bundle, so the real qty has to be derived from the child items.
     *
     * @param Order $order
     * @param array $qtys order_item_id => qty
     *
     * @return array
     */
    private function correctBundleParentQtys(Order $order, array $qtys): array
    {
        foreach ($order->getAllItems() as $orderItem) {
            if (empty($orderItem->getChildrenItems()) || !$orderItem->isShipSeparately()) {
                continue;
            }

            $parentQty = min(
                $this->getParentQtyFromChildren($orderItem, $qtys),
                (float)$orderItem->getQtyToInvoice()
            );

            if ($parentQty > 0) {
                $qtys[(int)$orderItem->getId()] = $parentQty;
            } else {
                unset($qtys[$orderItem->getId()]);
            }
        }

        return $qtys;
    }

    private function prepareInvoiceItems(Order $order, array $invoiceItems): array
    {
        if (empty($invoiceItems)) {
            return $this->getInvoiceItems($order);
        }

        return $this->addMissingBundleParentQtys($order, $invoiceItems);
    }

    /**
     * Add bundle parent qtys derived from their child items.
     *
     * The admin shipment form posts qtys keyed by the child order items for bundles set to
     * "Ship Separately", while invoicing a fixed-price bundle requires the parent item qty.
     * Core InvoiceService only propagates parent qty to children, never child to parent, so
     * without the parent qty the bundle is silently dropped from the invoice.
     *
     * @param Order $order
     * @param array $invoiceItems order_item_id => qty
     *
     * @return array
     */
    private function addMissingBundleParentQtys(Order $order, array $invoiceItems): array
    {
        foreach ($order->getAllItems() as $orderItem) {
            if (empty($orderItem->getChildrenItems()) || isset($invoiceItems[$orderItem->getId()])) {
                continue;
            }

            $parentQty = min(
                $this->getParentQtyFromChildren($orderItem, $invoiceItems),
                (float)$orderItem->getQtyToInvoice()
            );

            if ($parentQty > 0) {
                $invoiceItems[$orderItem->getId()] = $parentQty;
            }
        }

        return $invoiceItems;
    }

    /**
     * Derive the number of invoiceable parent (bundle) units from the child qtys provided.
     *
     * @param Order\Item $parentItem
     * @param array      $invoiceItems order_item_id => qty
     *
     * @return float
     */
    private function getParentQtyFromChildren(Order\Item $parentItem, array $invoiceItems): float
    {
        $parentQty = null;

        foreach ($parentItem->getChildrenItems() as $childItem) {
            if (!isset($invoiceItems[$childItem->getId()])) {
                continue;
            }

            $selectionQty = $this->getBundleSelectionQty($childItem);
            $childSets = $selectionQty > 0
                ? floor((float)$invoiceItems[$childItem->getId()] / $selectionQty)
                : 0.0;

            $parentQty = $parentQty === null ? $childSets : min($parentQty, $childSets);
        }

        return (float)($parentQty ?? 0);
    }

    /**
     * Get the qty of a child product included in a single bundle, defaults to 1.
     *
     * @param Order\Item $childItem
     *
     * @return float
     */
    private function getBundleSelectionQty(Order\Item $childItem): float
    {
        $productOptions = $childItem->getProductOptions();
        $selectionAttributes = $productOptions['bundle_selection_attributes'] ?? null;

        if (is_string($selectionAttributes)) {
            try {
                $selectionAttributes = $this->jsonSerializer->unserialize($selectionAttributes);
            } catch (\InvalidArgumentException $e) {
                $this->logger->addDebug(
                    __METHOD__ . ' - Could not unserialize bundle_selection_attributes: ' . $e->getMessage()
                );
                return 1.0;
            }
        }

        return isset($selectionAttributes['qty']) ? (float)$selectionAttributes['qty'] : 1.0;
    }
}
