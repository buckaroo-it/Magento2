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

namespace Buckaroo\Magento2\Console\Command;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Service\CreateInvoice;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RecoverKlarnaInvoices extends Command
{
    private const PAYMENT_METHOD = 'buckaroo_magento2_klarnakp';

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $orderCollectionFactory;

    /**
     * @var InvoiceService
     */
    private InvoiceService $invoiceService;

    /**
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var CreateInvoice
     */
    private CreateInvoice $createInvoiceService;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $invoiceSender;

    /**
     * @var Account
     */
    private Account $configAccount;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @var Log
     */
    private Log $logger;

    /**
     * @param CollectionFactory        $orderCollectionFactory
     * @param InvoiceService           $invoiceService
     * @param TransactionFactory       $transactionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CreateInvoice            $createInvoiceService
     * @param InvoiceSender            $invoiceSender
     * @param Account                  $configAccount
     * @param State                    $appState
     * @param Log                      $logger
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        OrderRepositoryInterface $orderRepository,
        CreateInvoice $createInvoiceService,
        InvoiceSender $invoiceSender,
        Account $configAccount,
        State $appState,
        Log $logger
    ) {
        parent::__construct();
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->orderRepository = $orderRepository;
        $this->createInvoiceService = $createInvoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->configAccount = $configAccount;
        $this->appState = $appState;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('buckaroo:klarna:recover-invoices')
            ->setDescription(
                'Creates offline invoices for Klarna KP orders that were captured by Plaza '
                . 'but have no invoice due to the auto-pay reservation conflict.'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List affected orders without making changes')
            ->addOption('order-id', null, InputOption::VALUE_REQUIRED, 'Process a single order increment ID');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set
        }

        $isDryRun = (bool)$input->getOption('dry-run');
        $specificOrderId = $input->getOption('order-id');

        if ($isDryRun) {
            $output->writeln('<info>DRY RUN — no changes will be made.</info>');
        }

        $orders = $this->getAffectedOrders($specificOrderId);

        if (empty($orders)) {
            $output->writeln('<info>No affected orders found.</info>');
            return Command::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $incrementId = $order->getIncrementId();

            if (!$this->hasValidBuckarooTransaction($order)) {
                $output->writeln(
                    "<comment>SKIP #{$incrementId}: no Buckaroo transaction key found (payment may not have been captured).</comment>"
                );
                $skipped++;
                continue;
            }

            $output->writeln("<info>Processing order #{$incrementId} (state: {$order->getState()})...</info>");

            if ($isDryRun) {
                $processed++;
                continue;
            }

            try {
                $this->recoverInvoice($order);
                $output->writeln("<info>  ✓ Invoice created for #{$incrementId}.</info>");
                $processed++;
            } catch (\Exception $e) {
                $output->writeln("<error>  ✗ Failed for #{$incrementId}: {$e->getMessage()}</error>");
                $this->logger->addError(
                    'RecoverKlarnaInvoices: failed for order #' . $incrementId . ': ' . $e->getMessage()
                );
                $failed++;
            }
        }

        $output->writeln('');
        $output->writeln("Done. Processed: {$processed}, Skipped: {$skipped}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Find Klarna KP orders with shipments but no invoices in closed/canceled state.
     *
     * @param  string|null $specificOrderId
     * @return Order[]
     */
    private function getAffectedOrders(?string $specificOrderId): array
    {
        $collection = $this->orderCollectionFactory->create();

        $collection->getSelect()
            ->joinInner(
                ['payment' => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = payment.parent_id',
                []
            )
            ->joinInner(
                ['shipment' => $collection->getTable('sales_shipment')],
                'main_table.entity_id = shipment.order_id',
                []
            )
            ->joinLeft(
                ['invoice' => $collection->getTable('sales_invoice')],
                'main_table.entity_id = invoice.order_id',
                []
            )
            ->where('payment.method = ?', self::PAYMENT_METHOD)
            ->where('main_table.state IN (?)', [Order::STATE_CLOSED, Order::STATE_CANCELED])
            ->where('invoice.entity_id IS NULL')
            ->group('main_table.entity_id');

        if ($specificOrderId !== null) {
            $collection->getSelect()->where('main_table.increment_id = ?', $specificOrderId);
        }

        return $collection->getItems();
    }

    /**
     * Check that the order payment has a Buckaroo transaction key (payment was captured).
     *
     * @param  Order $order
     * @return bool
     */
    private function hasValidBuckarooTransaction(Order $order): bool
    {
        $payment = $order->getPayment();
        $transactionKey = (string)$payment->getAdditionalInformation(
            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
        );
        return strlen($transactionKey) > 0;
    }

    /**
     * Reopen the order and create an offline invoice for it.
     *
     * @param  Order $order
     * @throws LocalizedException
     */
    private function recoverInvoice(Order $order): void
    {
        if ($order->getState() === Order::STATE_CANCELED) {
            $this->reopenCanceledOrder($order);
        } else {
            // Closed state: force back to processing so canInvoice() returns true.
            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        }

        // Fix data inconsistency: qty_invoiced may have been incremented during a failed
        // invoice attempt but no invoice record was saved, leaving qty_to_invoice = 0.
        if (!$order->hasInvoices()) {
            foreach ($order->getAllItems() as $item) {
                if ($item->getQtyInvoiced() > 0) {
                    $item->setQtyInvoiced(0);
                }
            }
            $order->setTotalInvoiced(0)->setBaseTotalInvoiced(0);
            $order->setTotalPaid(0)->setBaseTotalPaid(0);
            $order->setTotalDue($order->getGrandTotal())->setBaseTotalDue($order->getBaseGrandTotal());
        }

        if (!$order->canInvoice()) {
            throw new LocalizedException(__('Order #%1 cannot be invoiced.', $order->getIncrementId()));
        }

        $invoice = $this->invoiceService->prepareInvoice($order);

        if (!$invoice->getTotalQty()) {
            throw new LocalizedException(
                __('Cannot create invoice for order #%1: no items to invoice.', $order->getIncrementId())
            );
        }

        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->pay();

        $this->createInvoiceService->addTransactionData($order->getPayment());
        $invoice->setTransactionId(
            $order->getPayment()->getAdditionalInformation(AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
        );

        $order->addStatusHistoryComment(
            'Invoice recovered offline by buckaroo:klarna:recover-invoices (Plaza auto-capture conflict).',
            false
        );

        $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($order->getStore())) {
            $this->invoiceSender->send($invoice, true);
        }
    }

    /**
     * Reopen a canceled order by resetting item quantities and state.
     *
     * @param  Order $order
     */
    private function reopenCanceledOrder(Order $order): void
    {
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $item->setQtyCanceled(0);
                $item->setQtyInvoiced(0);
            }
        }

        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $order->setTotalCanceled(0)->setBaseTotalCanceled(0);
    }
}
