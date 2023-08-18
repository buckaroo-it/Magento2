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

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
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
     * @var PushRequestInterface
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
     * This is called when a refund is made in Buckaroo Payment Plaza.
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

        $this->logger->addDebug(
            __METHOD__ . '|1|Trying to refund order ' . $this->order->getId() . ' out of paymentplaza. '
        );

        if (!$this->configRefund->getAllowPush()) {
            $this->logger->addDebug(
                __METHOD__ . '|5|But failed, the configuration is set not to accept refunds out of Payment Plaza'
            );
            //phpcs:ignore:Magento2.Exceptions.DirectThrow
            throw new BuckarooException(
                __('Buckaroo refund is disabled')
            );
        }

        if (!$signatureValidation && !$this->order->canCreditmemo()) {
            $debugMessage = 'Validation incorrect: ' . PHP_EOL;
            //phpcs:ignore:Magento2.Functions.DiscouragedFunction
            $debugMessage .= print_r(
                [
                    'signature'      => $signatureValidation,
                    'canOrderCredit' => $this->order->canCreditmemo()
                ],
                true
            );
            $this->logger->addDebug($debugMessage);
            //phpcs:ignore:Magento2.Exceptions.DirectThrow
            throw new Exception(
                __('Buckaroo refund push validation failed')
            );
        }

        $creditmemoCollection = $this->order->getCreditmemosCollection();
        $creditmemosByTransactionId = $creditmemoCollection->getItemsByColumnValue(
            'transaction_id',
            $this->postData->getTransactions()
        );
        if (count($creditmemosByTransactionId) > 0) {
            $this->logger->addDebug(__METHOD__ . '|15|The transaction has already been refunded.');

            return false;
        }

        $creditmemo = $this->createCreditmemo();
        $this->logger->addDebug(__METHOD__ . '|20|Order successful refunded = ' . $creditmemo);

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
        $this->logger->addDebug(__METHOD__ . '|1|');
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
                    $this->logger->addDebug(__METHOD__ . '|5|');
                    $creditmemo->setBaseGrandTotal($this->totalAmountToRefund());
                    $creditmemo->setGrandTotal($this->totalAmountToRefund());
                }
                if (!$creditmemo->isValidGrandTotal()) {
                    $this->logger->addDebug(__METHOD__ . '|10|The credit memo\'s total must be positive.');
                    throw new LocalizedException(
                        __('The credit memo\'s total must be positive.')
                    );
                }
                $creditmemo->setTransactionId($this->postData->getTransactions());

                $this->logger->addDebug(__METHOD__ . '|20');
                $this->creditmemoManagement->refund(
                    $creditmemo,
                    (bool)$creditData['do_offline'],
                    !empty($creditData['order_email'])
                );
                $this->logger->addDebug(__METHOD__ . '|25');
                $this->creditEmailSender->send($creditmemo);
                return true;
            } else {
                $debugMessage = 'Failed to create the creditmemo, method saveCreditmemo return value: ' . PHP_EOL;
                //phpcs:ignore:Magento2.Functions.DiscouragedFunction
                $debugMessage .= print_r($creditmemo, true);
                $this->logger->addDebug(__METHOD__ . '|30|' . $debugMessage);
                //phpcs:ignore:Magento2.Exceptions.DirectThrow
                throw new BuckarooException(
                    __('Failed to create the creditmemo')
                );
            }
        } catch (LocalizedException $e) {
            $this->logger->addDebug(
                __METHOD__ . '|35|Buckaroo failed to create the credit memo\'s { ' . $e->getLogMessage() . ' }'
            );
        }
        $this->logger->addDebug(__METHOD__ . '|40');
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

        $this->logger->addDebug(__METHOD__ . '|1|' . var_export([
                $this->creditAmount,
                $this->order->getBaseGrandTotal(),
            ], true));

        if (!$this->helper->areEqualAmounts($this->creditAmount, $this->order->getBaseGrandTotal())) {
            $adjustment = $this->getAdjustmentRefundData();
            $this->logger->addDebug('This is an adjustment refund of ' . $totalAmountToRefund);
            $data['shipping_amount'] = '0';
            $data['adjustment_negative'] = '0';
            $data['adjustment_positive'] = $adjustment;
            $data['items'] = $this->getCreditmemoDataItems();
            $data['qtys'] = $this->setCreditQtys($data['items']);
        } else {
            $this->logger->addDebug(
                'With this refund of ' . $this->creditAmount . ' the grand total will be refunded.'
            );
            $data['shipping_amount'] = $this->caluclateShippingCostToRefund();
            $data['adjustment_negative'] = $this->getTotalCreditAdjustments();
            $data['adjustment_positive'] = $this->calculateRemainder();
            $data['items'] = $this->getCreditmemoDataItems();
            $data['qtys'] = $this->setCreditQtys($data['items']);
        }

        $debugMessage = 'Data used for credit nota: ' . PHP_EOL;
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $debugMessage .= print_r($data, true);
        $this->logger->addDebug($debugMessage);

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

        foreach ($this->order->getAllItems() as $orderItem) {
            /**
             * @var \Magento\Sales\Model\Order\Item $orderItem
             */
            if (!array_key_exists($orderItem->getId(), $items)) {
                if ($this->helper->areEqualAmounts($this->creditAmount, $this->order->getBaseGrandTotal())) {
                    $qty = $orderItem->getQtyInvoiced() - $orderItem->getQtyRefunded();
                }

                $items[$orderItem->getId()] = ['qty' => (int)$qty];
            }
        }

        $debugMessage = 'Total items to be refunded: ' . PHP_EOL;
        //phpcs:ignore:Magento2.Functions.DiscouragedFunction
        $debugMessage .= print_r($items, true);
        $this->logger->addDebug($debugMessage);

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
            $this->logger->addDebug(__METHOD__ . '|1|');
            return ($this->order->getBaseShippingAmount() + $this->order->getBaseShippingTaxAmount())
                - ($this->order->getBaseShippingRefunded() + $this->order->getBaseShippingTaxRefunded());
        } else {
            $this->logger->addDebug(__METHOD__ . '|2|');
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

        $this->logger->addDebug(__METHOD__ . '|5|' . var_export([
                $this->totalAmountToRefund(),
                $this->order->getBaseGrandTotal(),
                $remainderToRefund
            ], true));

        if ($this->totalAmountToRefund() == $this->order->getBaseGrandTotal()) {
            $this->logger->addDebug(__METHOD__ . '|10|');
            $remainderToRefund = 0;
        }

        if ($remainderToRefund < 0.01) {
            $this->logger->addDebug(__METHOD__ . '|15|');
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
            $this->logger
                ->addDebug('Buckaroo can not initialize the credit memo\'s by order { ' . $e->getLogMessage() . ' }');
        }
        return false;
    }
}
