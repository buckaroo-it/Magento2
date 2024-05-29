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
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
use Buckaroo\Magento2\Model\ConfigProvider\Refund as RefundConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\CreditmemoSender;

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
    public BuckarooLoggerInterface $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var CreditmemoManagementInterface
     */
    private $creditmemoManagement;

    /**
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param CreditmemoSender $creditEmailSender
     * @param Refund $configRefund
     * @param Data $helper
     * @param BuckarooLoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CreditmemoFactory $creditmemoFactory,
        CreditmemoManagementInterface $creditmemoManagement,
        CreditmemoSender $creditEmailSender,
        Refund $configRefund,
        Data $helper,
        BuckarooLoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoManagement = $creditmemoManagement;
        $this->creditEmailSender = $creditEmailSender;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->configRefund = $configRefund;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * This is called when a refund is made in Buckaroo Plaza.
     * This Function will result in a creditmemo being created for the order in question.
     *
     * @param PushRequestInterface $postData
     * @param bool $signatureValidation
     * @param $order
     *
     * @return bool
     * @throws BuckarooException
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

        if (!$signatureValidation && !$this->order->canCreditmemo()) {
            $this->logger->addDebug(sprintf(
                '[PUSH_REFUND] | [Webapi] | [%s:%s] - Refund order failed - validation incorrect | signature: %s',
                __METHOD__,
                __LINE__,
                var_export([
                    'signature'      => $signatureValidation,
                    'canOrderCredit' => $this->order->canCreditmemo()
                ], true)
            ));
            throw new BuckarooException(__('Buckaroo refund push validation failed'));
        }

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
            return false;
        }

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
     * Create the creditmemo
     *
     * @return bool
     * @throws LocalizedException
     */
    public function createCreditmemo(): bool
    {
        $creditData = $this->getCreditmemoData();
        $creditmemo = $this->initCreditmemo($creditData);

        try {
            if ($creditmemo) {
                if ($this->postData->hasAdditionalInformation('service_action_from_magento', 'capture')
                    && !empty($this->postData->getTransactionMethod())
                    && ($this->postData->getTransactionMethod() == 'afterpay')
                    && !empty($this->postData->getTransactionType())
                    && ($this->postData->getTransactionType() == 'C041')
                ) {
                    $creditmemo->setBaseGrandTotal($this->totalAmountToRefund());
                    $creditmemo->setGrandTotal($this->totalAmountToRefund());
                }
                if (!$creditmemo->isValidGrandTotal()) {
                    $this->logger->addDebug(sprintf(
                        '[PUSH_REFUND] | [Webapi] | [%s:%s] - The credit memo\'s total must be positive',
                        __METHOD__,
                        __LINE__
                    ));
                    throw new LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }
                $creditmemo->setTransactionId($this->postData->getTransactions());

                $this->creditmemoManagement->refund(
                    $creditmemo,
                    (bool)$creditData['do_offline'],
                    !empty($creditData['order_email'])
                );
                $this->creditEmailSender->send($creditmemo);
                return true;
            } else {
                $this->logger->addError(sprintf(
                    '[PUSH_REFUND] | [Webapi] | [%s:%s] - Failed to create the creditmemo' .
                    'method saveCreditmemo return value: %s',
                    __METHOD__,
                    __LINE__,
                    print_r($creditmemo, true)
                ));

                throw new BuckarooException(
                    __('Failed to create the creditmemo')
                );
            }
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

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [Webapi] | [%s:%s] - The credit memo data | data: %s',
            __METHOD__,
            __LINE__,
            print_r($data, true)
        ));

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

        if($refundedItems) {
            $items = $refundedItems;
        } else {
            foreach ($this->order->getAllItems() as $orderItem) {
                if (!array_key_exists($orderItem->getId(), $items)) {
                    if ($this->helper->areEqualAmounts($this->creditAmount, $this->order->getBaseGrandTotal())) {
                        $qty = $orderItem->getQtyInvoiced() - $orderItem->getQtyRefunded();
                    }

                    $items[$orderItem->getId()] = ['qty' => (int)$qty];
                }
            }
        }

        $this->logger->addDebug(sprintf(
            '[PUSH_REFUND] | [Webapi] | [%s:%s] - Total items to be refunded: %s',
            __METHOD__,
            __LINE__,
            print_r($items, true)
        ));

        return $items;
    }

    /**
     * Set quantity items
     *
     * @param array $items
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
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
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
     * Create credit memo by order and refund data
     *
     * @param array $creditData
     * @return Creditmemo|false
     * @throws LocalizedException
     */
    public function initCreditmemo(array $creditData)
    {
        try {
            $creditmemo = $this->creditmemoFactory->createByOrder($this->order, $creditData);

            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $creditmemoItem->setBackToStock(false);
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
