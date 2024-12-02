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

namespace Buckaroo\Magento2\Helper;

use \Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;

class PaymentFee extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Buckaroo\Magento2\Model\ConfigProvider\Account */
    protected $configProviderAccount;

    /** @var \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee */
    protected $configProviderBuckarooFee;

    /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory */
    protected $configProviderMethodFactory;

    public $buckarooFee = false;

    public $buckarooFeeTax = false;

    protected $groupTransaction;

    /**
     * @var \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection
     */
    protected $giftcardCollection;

    /**
     * @param \Magento\Framework\App\Helper\Context             $context
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account        $configProviderAccount
     * @param \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee    $configProviderBuckarooFee
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory,
        PaymentGroupTransaction $groupTransaction,
        \Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection $giftcardCollection
    ) {
        parent::__construct($context);

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
    }

    /**
     * Return totals of data object
     *
     * @param  \Magento\Framework\DataObject $dataObject
     * @return array
     */
    public function getTotals($dataObject)
    {
        $totals = [];

        $taxClassId = $this->configProviderAccount->getBuckarooFeeTaxClass();
        $label = $this->getBuckarooPaymentFeeLabel($dataObject);

        $fee = $dataObject->getBuckarooFee();
        $feeTaxAmount = $dataObject->getBuckarooFeeTaxAmount();
        $baseFee = $dataObject->getBaseBuckarooFee();
        $baseFeeTaxAmount = $dataObject->getBuckarooFeeBaseTaxAmount();

        // Check the setting to determine if the fee should include tax
        if ($taxClassId && $this->buckarooPaymentCalculationInclTax()) {
            // Add the fee with tax included
            $this->addTotalToTotals(
                $totals,
                'buckaroo_fee_incl',
                $fee + $feeTaxAmount,
                $baseFee + $baseFeeTaxAmount,
                $label . __(' (Incl. Tax)')
            );
        } else {
            // Add the fee without tax
            $this->addTotalToTotals(
                $totals,
                'buckaroo_fee',
                $fee,
                $baseFee,
                $label . __(' (Excl. Tax)')
            );
        }

        $this->addAlreadyPayedTotals($dataObject, $totals);

        $this->buckarooFee = $fee;
        $this->buckarooFeeTax = $feeTaxAmount;
        return $totals;
    }

    public function buckarooPaymentCalculationInclTax($store = null)
    {
        $configValue = $this->configProviderBuckarooFee->getPaymentFeeTax($store);

        return $configValue == DisplayType::DISPLAY_TYPE_INCLUDING_TAX;
    }
    public function addAlreadyPayedTotals($dataObject, &$totals)
    {
        $order_id = $this->getOrderIncrementId($dataObject);
        $alreadyPayed = $this->groupTransaction->getAlreadyPaid($order_id);

        if (!$dataObject instanceof \Magento\Sales\Model\Order\Creditmemo && $alreadyPayed > 0) {
            unset($totals['buckaroo_fee']);
            $this->addTotalToTotals(
                $totals,
                'buckaroo_already_paid',
                $alreadyPayed,
                $alreadyPayed,
                __('Paid with Giftcard / Voucher')
            );
            return;
        }

        if ($order_id !== null && $alreadyPayed > 0) {

            $requestParams = $this->_request->getParams();
            $items = $this->groupTransaction->getGroupTransactionItems($order_id);
            $giftcards = [];

            if (isset($requestParams['creditmemo']['buckaroo_already_paid'])) {
                foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $giftcardKey => $value) {
                    $transaction = explode('|', $giftcardKey);
                    $giftcards[$transaction[1]] = $value;
                }
            }
            foreach ($items as $key => $giftcard) {
                $foundGiftcard = $this->giftcardCollection->getItemByColumnValue(
                    'servicecode',
                    $giftcard['servicecode']
                );

                $label = __('Paid with Voucher');
                if ($foundGiftcard) {
                    $label = __('Paid with ' . $foundGiftcard['label']);
                }

                $refundedAlreadyPaidSaved = $giftcard->getRefundedAmount() ?? 0;
                $amountValue = $giftcard['amount'];
                $amountBaseValue = $giftcard['amount'];

                if (!empty($foundGiftcard['is_partial_refundable'])) {
                    $residual = floatval($giftcard['amount']) - floatval($refundedAlreadyPaidSaved);
                    if (
                        array_key_exists($foundGiftcard['servicecode'], $giftcards)
                        && floatval($giftcards[$foundGiftcard['servicecode']]) <= $residual
                    ) {
                        $amountValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                        $amountBaseValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                    } else {
                        $amountBaseValue = $residual;
                        $amountValue = $residual;
                    }
                } else {
                    if ((!empty(floatval($refundedAlreadyPaidSaved))
                        && floatval($refundedAlreadyPaidSaved) === floatval($amountValue))) {
                        $amountBaseValue = 0;
                        $amountValue = 0;
                    } elseif (is_array($foundGiftcard) && array_key_exists($foundGiftcard['servicecode'], $giftcards)) {
                        if (empty(floatval($giftcards[$foundGiftcard['servicecode']]))) {
                            $amountBaseValue = 0;
                            $amountValue = 0;
                        }
                    }
                }

                $this->addTotalToTotals(
                    $totals,
                    'buckaroo_already_paid',
                    -$amountValue,
                    -$amountBaseValue,
                    $label,
                    'buckaroo_already_paid',
                    $giftcard['transaction_id'] . '|' . $giftcard['servicecode'] . '|' . $giftcard['amount']
                );
            }
        }
    }

    /**
     * Get order increment id from data object
     *
     * @param mixed $dataObject
     *
     * @return string|null
     */
    public function getOrderIncrementId($dataObject)
    {
        if ($dataObject instanceof \Magento\Sales\Model\Order) {
            return $dataObject->getIncrementId();
        }
        if (
            $dataObject instanceof \Magento\Sales\Model\Order\Invoice
            || $dataObject instanceof \Magento\Sales\Model\Order\Creditmemo
        ) {
            return $dataObject->getOrder()->getIncrementId();
        }
    }

    /**
     * @return mixed
     */
    public function getBuckarooFee()
    {
        return $this->buckarooFee;
    }

    /**
     * @return mixed
     */
    public function getBuckarooFeeTax()
    {
        return $this->buckarooFeeTax;
    }

    /**
     * Return the correct label for the payment method
     *
     * @param $dataObject
     *
     * @return string
     */
    public function getBuckarooPaymentFeeLabel($dataObject)
    {
        $method = false;
        $label = false;

        /**
         * Parse data object for payment method
         */
        if ($dataObject instanceof \Magento\Sales\Model\Order) {
            $method = $dataObject->getPayment()->getMethod();
        } elseif (
            $dataObject instanceof \Magento\Sales\Model\Order\Invoice
            || $dataObject instanceof \Magento\Sales\Model\Order\Creditmemo
        ) {
            $method = $dataObject->getOrder()->getPayment()->getMethod();
        } elseif (is_string($dataObject)) {
            $method = $dataObject;
        }

        /**
         * If a method is found, and the method has a config provider, try to get the label from config
         */
        if ($method && $this->configProviderMethodFactory->has($method)) {
            $label = $this->configProviderMethodFactory->get($method)->getPaymentFeeLabel();
        }

        /**
         * If no label is set yet, get the default configurable label
         */
        if (!$label) {
            $label = $this->configProviderAccount->getPaymentFeeLabel();
        }

        /**
         * If no label is set yet, return a default label
         */
        if (!$label) {
            $label = __('Buckaroo Fee');
        }

        return $label;
    }

    /**
     * Add total into array totals
     *
     * @param  array  &$totals
     * @param  string $code
     * @param  float  $value
     * @param  float  $baseValue
     * @param  string $label
     * @return void
     */
    protected function addTotalToTotals(
        &$totals,
        $code,
        $value,
        $baseValue,
        $label,
        $block_name = false,
        $transaction_id = false,
        $extra_info = []
    ) {
        if ($value == 0 && $baseValue == 0) {
            return;
        }
        $total = [
            'code' => $code,
            'value' => $value,
            'base_value' => $baseValue,
            'label' => $label,
            'extra_info' => $extra_info
        ];
        if ($block_name) {
            $total['block_name'] = $block_name;
        }
        if ($transaction_id) {
            $total['transaction_id'] = $transaction_id;
        }
        $totals[] = $total;
    }
}
