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

namespace Buckaroo\Magento2\Model\Refund;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
use Buckaroo\Magento2\Model\ConfigProvider\Refund as RefundConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Push
{
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    /**
     * @var \Buckaroo\Magento2\Api\Data\PushRequestInterface
     */
    public $postData;

    /**
     * @var float|null
     */
    public $creditAmount;

    /**
     * @var Order $order
     */
    public $order;

    /**
     * @var CreditmemoFactory $creditmemoFactory
     */
    public $creditmemoFactory;

    /**
     * @var CreditmemoSender $creditEmailSender
     */
    public $creditEmailSender;

    /**
     * @var Refund
     */
    public $configRefund;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @var BuckarooLoggerInterface $logger
     */
    public $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CreditmemoManagementInterface
     */
    private $creditmemoManagement;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var Transaction
     */
    private $transaction;

    /**
     * @param CreditmemoFactory             $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param CreditmemoSender              $creditEmailSender
     * @param Refund                        $configRefund
     * @param Data                          $helper
     * @param BuckarooLoggerInterface       $logger
     * @param ScopeConfigInterface          $scopeConfig
     * @param InvoiceService                $invoiceService
     * @param Transaction                   $transaction
     */
    public function __construct(
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        CreditmemoSender $creditEmailSender,
        Refund $configRefund,
        Data $helper,
        BuckarooLoggerInterface $logger,
        ScopeConfigInterface $scopeConfig,
        InvoiceService $invoiceService,
        Transaction $transaction
    ) {
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->creditEmailSender = $creditEmailSender;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->configRefund = $configRefund;
        $this->scopeConfig = $scopeConfig;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
    }

    /**
     * This is called when a refund is made in Buckaroo Plaza.
     * This Function will result in a creditmemo being created for the order in question.
     *
     * @param PushRequestInterface $postData
     * @param bool                 $signatureValidation
     * @param                      $order
     *
     * @return bool
     * @throws BuckarooException|LocalizedException
     *
     */
    public function receiveRefundPush(PushRequestInterface $postData, bool $signatureValidation, $order): bool
    {
        $this->postData = $postData;
        $this->order = $order;

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [Webapi] | [%s:%s] - Trying to refund order out of paymentplaza | orderId: %s',
            __METHOD__,
            __LINE__,
            $this->order->getId()
        ));

        $this->validateRefundConfiguration();
        $this->validateRefundRequest($signatureValidation);

        if ($this->isAlreadyRefunded()) {
            return false;
        }

        $this->validateRefundStatus();

        $creditmemo = $this->createCreditmemo();

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [Webapi] | [%s:%s] - Order successful refunded | creditmemo: %s',
            __METHOD__,
            __LINE__,
            $creditmemo ? 'success' : 'false'
        ));

        return $creditmemo;
    }

    /**
     * Validate refund configuration
     *
     * @throws BuckarooException
     */
    private function validateRefundConfiguration(): void
    {
        if (!$this->configRefund->getAllowPush()) {
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Refund order failed - ' .
                'the configuration is set not to accept refunds out of Buckaroo Plaza | orderId: %s',
                __METHOD__,
                __LINE__,
                $this->order->getId()
            ));
            throw new BuckarooException(__('Buckaroo refund is disabled'));
        }
    }

    /**
     * Validate refund request
     *
     * @param bool $signatureValidation
     * @throws BuckarooException
     */
    private function validateRefundRequest(bool $signatureValidation): void
    {
        $canRefund = $this->canOrderBeRefunded();

        if (!$signatureValidation && !$canRefund) {
            $payment = $this->order->getPayment();
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Refund order failed - validation incorrect | signature: %s',
                __METHOD__,
                __LINE__,
                var_export([
                    'signature'      => $signatureValidation,
                    'canOrderCredit' => $this->order->canCreditmemo(),
                    'baseAmountPaid' => $payment ? $payment->getBaseAmountPaid() : 0,
                    'baseTotalRefunded' => $this->order->getBaseTotalRefunded()
                ], true)
            ));
            throw new BuckarooException(__('Buckaroo refund push validation failed'));
        }
    }

    /**
     * Check if order can be refunded
     *
     * @return bool
     */
    private function canOrderBeRefunded(): bool
    {
        if ($this->order->canCreditmemo()) {
            return true;
        }

        // Check if this is a captured order without invoice (deferred invoice mode)
        $payment = $this->order->getPayment();
        $isCaptured = $payment && $payment->getBaseAmountPaid() > 0;

        return $isCaptured && ($this->order->getBaseTotalRefunded() < $payment->getBaseAmountPaid());
    }

    /**
     * Check if transaction is already refunded
     *
     * @return bool
     */
    private function isAlreadyRefunded(): bool
    {
        $creditmemoCollection = $this->order->getCreditmemosCollection();
        $creditmemosByTransactionId = $creditmemoCollection->getItemsByColumnValue(
            'transaction_id',
            $this->postData->getTransactions()
        );

        if (count($creditmemosByTransactionId) > 0) {
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - The transaction has already been refunded',
                __METHOD__,
                __LINE__
            ));
            return true;
        }

        return false;
    }

    /**
     * Validate refund status from Buckaroo
     *
     * @throws BuckarooException
     */
    private function validateRefundStatus(): void
    {
        $statusCode = (int)$this->postData->getStatusCode();
        if ($statusCode !== BuckarooStatusCode::SUCCESS) {
            $this->logger->addError(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Refund FAILED at Buckaroo | Status: %s | Message: %s | Order: %s',
                __METHOD__,
                __LINE__,
                $statusCode,
                $this->postData->getStatusMessage(),
                $this->order->getIncrementId()
            ));
            throw new BuckarooException(__(
                'Buckaroo refund failed with status %1: %2',
                $statusCode,
                $this->postData->getStatusMessage()
            ));
        }
    }

    /**
     * Create the creditmemo
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    public function createCreditmemo(): bool
    {
        $creditData = $this->getCreditmemoData();
        $creditmemo = $this->initCreditmemo($creditData);

        try {
            if (!$creditmemo) {
                throw new BuckarooException(__('Failed to create the creditmemo'));
            }

            $this->applyRivertyRefundAdjustments($creditmemo);
            $this->validateCreditmemo($creditmemo);
            $this->processRefund($creditmemo, $creditData);

            return true;
        } catch (LocalizedException $e) {
            $this->logger->addError(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Buckaroo failed to create the credit memo\'s | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $e->getLogMessage()
            ));
        }
        return false;
    }

    /**
     * Apply Riverty-specific refund adjustments
     *
     * @param Creditmemo $creditmemo
     */
    private function applyRivertyRefundAdjustments(Creditmemo $creditmemo): void
    {
        $isRivertyRefund = $this->isRivertyRefund();
        $hasNoInvoice = !$this->order->hasInvoices();

        if ($isRivertyRefund && ($hasNoInvoice || $this->postData->hasAdditionalInformation('service_action_from_magento', 'capture'))) {
            $refundAmount = $this->totalAmountToRefund();
            $creditmemo->setBaseGrandTotal($refundAmount);
            $creditmemo->setGrandTotal($refundAmount);

            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Explicitly set credit memo grand total for Riverty deferred invoice: %s',
                __METHOD__,
                __LINE__,
                $refundAmount
            ));
        }
    }

    /**
     * Check if this is a Riverty refund
     *
     * @return bool
     */
    private function isRivertyRefund(): bool
    {
        return !empty($this->postData->getTransactionMethod())
            && ($this->postData->getTransactionMethod() == 'afterpay')
            && !empty($this->postData->getTransactionType())
            && ($this->postData->getTransactionType() == 'C041');
    }

    /**
     * Validate creditmemo
     *
     * @param Creditmemo $creditmemo
     * @throws LocalizedException
     */
    private function validateCreditmemo(Creditmemo $creditmemo): void
    {
        if (!$creditmemo->isValidGrandTotal()) {
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - The credit memo\'s total must be positive',
                __METHOD__,
                __LINE__
            ));
            throw new LocalizedException(__('The credit memo\'s total must be positive.'));
        }
    }

    /**
     * Process the refund
     *
     * @param Creditmemo $creditmemo
     * @param array $creditData
     * @throws \Exception
     */
    private function processRefund(Creditmemo $creditmemo, array $creditData): void
    {
        $creditmemo->setTransactionId($this->postData->getTransactions());

        $this->creditmemoManagement->refund(
            $creditmemo,
            (bool)$creditData['do_offline'],
            !empty($creditData['order_email'])
        );

        $this->creditEmailSender->send($creditmemo);
    }

    /**
     * Create array of data to use within the creditmemo.
     *
     * @return array
     */
    public function getCreditmemoData(): array
    {
        $data = [
            'do_offline'   => '0',
            'do_refund'    => '0',
            'comment_text' => ' '
        ];

        $totalAmountToRefund = $this->totalAmountToRefund();
        $this->creditAmount = $totalAmountToRefund + $this->order->getBaseTotalRefunded();

        if (!$this->helper->areEqualAmounts($this->creditAmount, $this->order->getBaseGrandTotal())) {
            $adjustment = $this->getAdjustmentRefundData();
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - This is an adjustment refund of %s',
                __METHOD__,
                __LINE__,
                $totalAmountToRefund
            ));
            $data['shipping_amount'] = '0';
            $data['adjustment_negative'] = '0';
            $data['adjustment_positive'] = $adjustment;
            $data['items'] = $this->getCreditmemoDataItems();
            $data['qtys'] = $this->setCreditQtys($data['items']);
        } else {
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - With this refund of %s the grand total will be refunded',
                __METHOD__,
                __LINE__,
                $this->creditAmount
            ));
            $data['shipping_amount'] = $this->caluclateShippingCostToRefund();
            $data['adjustment_negative'] = $this->getTotalCreditAdjustments();
            $data['adjustment_positive'] = $this->calculateRemainder();
            $data['items'] = $this->getCreditmemoDataItems();
            $data['qtys'] = $this->setCreditQtys($data['items']);
        }

        return $data;
    }

    /**
     * Calculate the amount to be refunded.
     *
     * @return int|float $amount
     */
    public function totalAmountToRefund()
    {
        if ($this->postData->getCurrency() == $this->order->getBaseCurrencyCode()) {
            $amount = $this->postData->getAmountCredit();
        } else {
            $amount = round($this->postData->getAmountCredit() / $this->order->getBaseToOrderRate(), 2);
            if ($amount > $this->order->getBaseGrandTotal()) {
                $amount = $this->order->getBaseGrandTotal();
            }
        }

        return $amount;
    }

    /**
     * Get adjustment refund data
     *
     * @return float
     */
    public function getAdjustmentRefundData()
    {
        $totalAmount = $this->totalAmountToRefund();

        if ($this->order->getBaseTotalRefunded() == null) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $totalAmount = $totalAmount -
                $this->order->getBaseBuckarooFeeInvoiced() -
                $this->order->getBuckarooFeeBaseTaxAmountInvoiced();
        }

        return $totalAmount;
    }

    /**
     * Check if there are items to correct on the creditmemo
     *
     * @return array $items
     */
    public function getCreditmemoDataItems(): array
    {
        $items = [];
        $qty = 0;

        $refundedItems = $this->order->getPayment()->getAdditionalInformation(RefundConfigProvider::ADDITIONAL_INFO_PENDING_REFUND_ITEMS);

        if ($refundedItems) {
            $items = $refundedItems;
        } else {
            foreach ($this->order->getAllItems() as $orderItem) {
                if (!array_key_exists($orderItem->getId(), $items)) {
                    if ($this->helper->areEqualAmounts($this->creditAmount, $this->order->getBaseGrandTotal())) {
                        // Check if item has been invoiced
                        if ($orderItem->getQtyInvoiced() > 0) {
                            // Standard flow: refund based on invoiced quantity
                            $qty = $orderItem->getQtyInvoiced() - $orderItem->getQtyRefunded();
                        } else {
                            // Deferred invoice flow (e.g., Riverty/Afterpay with SHIPMENT mode):
                            // If no invoice exists yet but the order was captured, allow refund based on ordered qty
                            $payment = $this->order->getPayment();
                            $isCaptured = $payment && $payment->getBaseAmountPaid() > 0;

                            if ($isCaptured) {
                                $qty = $orderItem->getQtyOrdered() - $orderItem->getQtyRefunded();
                                $this->logger->addDebug(sprintf(
                                    '[PUSH_REFUND] | [Webapi] | [%s:%s] - Deferred invoice mode detected - using ordered qty for item %s: %s',
                                    __METHOD__,
                                    __LINE__,
                                    $orderItem->getSku(),
                                    $qty
                                ));
                            }
                        }
                    }

                    $items[$orderItem->getId()] = ['qty' => (int)$qty];
                }
            }
        }

        return $items;
    }

    /**
     * Set quantity items
     *
     * @param array $items
     *
     * @return array $qtys
     */
    public function setCreditQtys($items): array
    {
        $qtys = [];

        if (!empty($items)) {
            foreach ($items as $orderItemId => $itemData) {
                $qtys[$orderItemId] = $itemData['qty'];
            }
        }

        return $qtys;
    }

    /**
     * Calculate the total of shipping cost to be refunded.
     *
     * @return float
     */
    public function caluclateShippingCostToRefund(): float
    {
        $includesTax = $this->scopeConfig->getValue(
            static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE
        );

        if ($includesTax) {
            return ($this->order->getBaseShippingAmount() + $this->order->getBaseShippingTaxAmount())
                - ($this->order->getBaseShippingRefunded() + $this->order->getBaseShippingTaxRefunded());
        } else {
            return $this->order->getBaseShippingAmount()
                - $this->order->getBaseShippingRefunded();
        }
    }

    /**
     * Get total of adjustments made by previous credits.
     *
     * @return int|float
     */
    public function getTotalCreditAdjustments()
    {
        $totalAdjustments = 0;

        foreach ($this->order->getCreditmemosCollection() as $creditmemo) {
            /**
             * @var Creditmemo $creditmemo
             */
            $adjustment = $creditmemo->getBaseAdjustmentPositive() - $creditmemo->getBaseAdjustmentNegative();
            $totalAdjustments += $adjustment;
        }

        return $totalAdjustments;
    }

    /**
     * Calculate the remainder of to be refunded
     *
     * @return float
     */
    public function calculateRemainder()
    {
        $baseTotalToBeRefunded = $this->caluclateShippingCostToRefund() +
            ($this->order->getBaseSubtotal() - $this->order->getBaseSubtotalRefunded()) +
            ($this->order->getBaseAdjustmentNegative() - $this->order->getBaseAdjustmentPositive()) +
            ($this->order->getBaseTaxAmount() - $this->order->getBaseTaxRefunded()) +
            ($this->order->getBaseDiscountAmount() - $this->order->getBaseDiscountRefunded());

        $remainderToRefund = $this->order->getBaseGrandTotal()
            - $baseTotalToBeRefunded
            - $this->order->getBaseTotalRefunded();

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [Webapi] | [%s:%s] - Calculate the remainder | totals: %s',
            __METHOD__,
            __LINE__,
            var_export([
                'totalAmountToRefund' => $this->totalAmountToRefund(),
                'orderBaseGrandTotal' => $this->order->getBaseGrandTotal(),
                'remainderToRefund' => $remainderToRefund
            ], true)
        ));

        if ($this->totalAmountToRefund() == $this->order->getBaseGrandTotal()) {
            $remainderToRefund = 0;
        }

        if ($remainderToRefund < 0.01) {
            $remainderToRefund = 0;
        }

        return $remainderToRefund;
    }

    /**
     * Create invoice for order without invoice (deferred invoice mode)
     *
     * @throws LocalizedException
     * @return void
     */
    private function createInvoiceForOrder()
    {
        try {
            if ($this->order->hasInvoices()) {
                $this->logger->addDebug(sprintf(
                    '[PUSH_REFUND] | [Webapi] | [%s:%s] - Order already has invoices, skipping invoice creation',
                    __METHOD__,
                    __LINE__
                ));
                return;
            }

            // Create invoice for all items using InvoiceService
            $invoice = $this->invoiceService->prepareInvoice($this->order);

            if (!$invoice || !$invoice->getTotalQty()) {
                throw new LocalizedException(__('Cannot create an invoice without products.'));
            }

            // Register invoice as captured offline (since payment was already captured in Buckaroo)
            // Use CAPTURE_OFFLINE to register the invoiced amount without actually capturing payment again
            $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            // Save invoice and order in a transaction
            $invoice->getOrder()->setIsInProcess(true);

            $this->transaction
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Invoice created successfully for order %s | Invoice ID: %s | Grand Total: %s',
                __METHOD__,
                __LINE__,
                $this->order->getIncrementId(),
                $invoice->getIncrementId(),
                $invoice->getGrandTotal()
            ));
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Failed to create invoice for order %s | Error: %s',
                __METHOD__,
                __LINE__,
                $this->order->getIncrementId(),
                $e->getMessage()
            ));
            throw new LocalizedException(
                __('Cannot create invoice for refund: %1', $e->getMessage())
            );
        }
    }

    /**
     * Create credit memo by order and refund data
     *
     * @param array $creditData
     *
     * @throws LocalizedException
     *
     * @return Creditmemo|false
     */
    public function initCreditmemo(array $creditData)
    {
        try {
            // Check if order has no invoices (deferred invoice mode)
            $hasNoInvoice = !$this->order->hasInvoices();

            if ($hasNoInvoice) {
                $this->logger->addDebug(sprintf(
                    '[PUSH_REFUND] | [Webapi] | [%s:%s] - Order has no invoice - creating invoice first for deferred invoice mode',
                    __METHOD__,
                    __LINE__
                ));

                // For Riverty/Afterpay with deferred invoicing, we need to create an invoice first
                // before we can create a credit memo
                $this->createInvoiceForOrder();
            }

            $creditmemo = $this->creditmemoFactory->createByOrder($this->order, $creditData);

            if (!$creditmemo) {
                $this->logger->addError(sprintf(
                    '[PUSH_REFUND] | [Webapi] | [%s:%s] - Failed to create credit memo from order',
                    __METHOD__,
                    __LINE__
                ));
                return false;
            }

            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Credit memo created | Items count: %s | Grand Total: %s | Base Grand Total: %s',
                __METHOD__,
                __LINE__,
                count($creditmemo->getAllItems()),
                $creditmemo->getGrandTotal(),
                $creditmemo->getBaseGrandTotal()
            ));

            // Respect Magento's auto-return configuration instead of hardcoding to false
            $autoReturn = $this->scopeConfig->isSetFlag(
                'cataloginventory/item_options/auto_return',
                ScopeInterface::SCOPE_STORE,
                $this->order->getStoreId()
            );

            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $creditmemoItem->setBackToStock($autoReturn);
            }

            return $creditmemo;
        } catch (LocalizedException $e) {
            $this->logger->addError(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Buckaroo can not initialize the credit memo\'s by order: %s',
                __METHOD__,
                __LINE__,
                $e->getLogMessage()
            ));
        }
        return false;
    }
}
