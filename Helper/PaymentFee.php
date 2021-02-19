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
        $displayBothPrices = false;
        $displayIncludeTaxPrice = false;
        $requestParams = $this->_request->getParams();

        if ($dataObject instanceof \Magento\Sales\Model\Order
            || $dataObject instanceof \Magento\Sales\Model\Order\Invoice
            || $dataObject instanceof \Magento\Sales\Model\Order\Creditmemo
        ) {
            $displayBothPrices = $this->displaySalesBothPrices();
            $displayIncludeTaxPrice = $this->displaySalesIncludeTaxPrice();
        } elseif ($dataObject instanceof \Magento\Quote\Model\Quote\Address\Total) {
            $displayBothPrices = $this->displayCartBothPrices();
            $displayIncludeTaxPrice = $this->displayCartIncludeTaxPrice();
        }

        $label = $this->getBuckarooPaymentFeeLabel($dataObject);

        /**
         * Buckaroo fee for order totals
         */
        if ($displayBothPrices || $displayIncludeTaxPrice) {
            if ($displayBothPrices) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $this->addTotalToTotals(
                    $totals,
                    'buckaroo_fee_excl',
                    $dataObject->getBuckarooFee(),
                    $dataObject->getBaseBuckarooFee(),
                    $label . __(' (Excl. Tax)')
                );
            }
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $this->addTotalToTotals(
                $totals,
                'buckaroo_fee_incl',
                $dataObject->getBuckarooFee() + $dataObject->getBuckarooFeeTaxAmount(),
                $dataObject->getBaseBuckarooFee() + $dataObject->getBuckarooFeeBaseTaxAmount(),
                $label . __(' (Incl. Tax)')
            );
        } elseif ($dataObject instanceof \Magento\Sales\Model\Order\Creditmemo) {
            $method = $dataObject->getOrder()->getPayment()->getMethod();
            if (!preg_match('/afterpay/', $method) || (strpos($method, 'afterpay20') !== false)) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $this->addTotalToTotals(
                    $totals,
                    'buckaroo_fee',
                    $dataObject->getBuckarooFee(),
                    $dataObject->getBaseBuckarooFee(),
                    $label,
                    'buckaroo_fee'
                );
            } else {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $this->addTotalToTotals(
                    $totals,
                    'buckaroo_fee',
                    $dataObject->getBuckarooFee(),
                    $dataObject->getBaseBuckarooFee(),
                    $label,
                    'buckaroo_fee_afterpay'
                );
            }
        } else {
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $this->addTotalToTotals(
                $totals,
                'buckaroo_fee',
                $dataObject->getBuckarooFee(),
                $dataObject->getBaseBuckarooFee(),
                $label
            );
        }

        if ($dataObject instanceof \Magento\Sales\Model\Order) {
            $order_id = $dataObject->getIncrementId();
        } elseif ($dataObject instanceof \Magento\Sales\Model\Order\Invoice
            || $dataObject instanceof \Magento\Sales\Model\Order\Creditmemo
        ) {
            $order = $dataObject->getOrder();
            $order_id = $order->getIncrementId();
        }

        $order = $dataObject->getOrder();
        if ($dataObject->getBaseBuckarooAlreadyPaid()) {
            unset($totals['buckaroo_fee']);
            $this->addTotalToTotals(
                $totals,
                'buckaroo_already_paid',
                $dataObject->getBuckarooAlreadyPaid(),
                $dataObject->getBaseBuckarooAlreadyPaid(),
                __('Paid with Giftcard')
            );
        } elseif (isset($order) && $order->getBuckarooAlreadyPaid()) {
            $items = $this->groupTransaction->getGroupTransactionItems($order->getIncrementId());
            $giftcards = [];

            if (isset($requestParams['creditmemo']['buckaroo_already_paid'])) {
                foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $giftcardKey => $value) {
                    $transaction = explode('|', $giftcardKey);
                    $giftcards[$transaction[1]] = $value;
                }
            }
            foreach ($items as $key => $giftcard) {
                $foundGiftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $giftcard['servicecode']);
                $label = __('Paid with ' . $foundGiftcard['label']);

                $refundedAlreadyPaidSaved = $giftcard->getRefundedAmount() ?? 0;
                $amountValue = $giftcard['amount'];
                $amountBaseValue = $giftcard['amount'];

                if (!empty($foundGiftcard['is_partial_refundable'])) {
                    $residual = floatval($giftcard['amount']) - floatval($refundedAlreadyPaidSaved);
                    if (array_key_exists($foundGiftcard['servicecode'], $giftcards) && floatval($giftcards[$foundGiftcard['servicecode']]) <= $residual) {
                        $amountValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                        $amountBaseValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                    } else {
                        $amountBaseValue = $residual;
                        $amountValue = $residual;
                    }
                } else {
                    if ((!empty(floatval($refundedAlreadyPaidSaved)) && floatval($refundedAlreadyPaidSaved) === floatval($amountValue))) {
                        $amountBaseValue = 0;
                        $amountValue = 0;
                    } elseif (array_key_exists($foundGiftcard['servicecode'], $giftcards)) {
                        if (empty(floatval($giftcards[$foundGiftcard['servicecode']]))) {
                            $amountBaseValue = 0;
                            $amountValue = 0;
                        }
                    }
                }

                $this->addTotalToTotals(
                    $totals,
                    'buckaroo_already_paid',
                    - $amountValue,
                    - $amountBaseValue,
                    $label,
                    'buckaroo_already_paid',
                    $giftcard['transaction_id'].'|'.$giftcard['servicecode'].'|'.$giftcard['amount']
                );
            }
        }

        //Set public object data
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->buckarooFee    = $dataObject->getBuckarooFee();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->buckarooFeeTax = $dataObject->getBuckarooFeeTaxAmount();
        return $totals;
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
        } elseif ($dataObject instanceof \Magento\Sales\Model\Order\Invoice
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
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
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
     * @param \Magento\Framework\DataObject $dataObject
     *
     * @return array
     */
    public function getBuckarooPaymentFeeTotal($dataObject)
    {
        $totals = [];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->addTotalToTotals(
            $totals,
            'buckaroo_fee',
            $dataObject->getBuckarooFee() + $dataObject->getBuckarooFeeTaxAmount(),
            $dataObject->getBaseBuckarooFee() + $dataObject->getBuckarooFeeBaseTaxAmount(),
            $this->getBuckarooPaymentFeeLabel($dataObject)
        );

        return $totals;
    }

    /**
     * Check if the fee calculation has to be done with taxes
     *
     * @param \Magento\Store\Model\Store|int|null $store
     *
     * @return bool
     */
    public function buckarooPaymentCalculationInclTax($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPaymentFeeTax($store);

        return $configValue == DisplayType::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if the fee calculation has to be done without  taxes
     *
     * @param \Magento\Store\Model\Store|int|null $store
     *
     * @return bool
     */
    public function buckarooPaymentFeeCalculationExclTax($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPaymentFeeTax($store);

        return $configValue == DisplayType::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check ability to display prices including tax for buckaroo fee in shopping cart
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displayCartIncludeTaxPrice($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplayCart($store);

        return $configValue == DisplayType::DISPLAY_TYPE_BOTH ||
            $configValue == DisplayType::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check ability to display prices excluding tax for buckaroo fee in shopping cart
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displayCartExcludeTaxPrice($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplayCart($store);

        return $configValue == DisplayType::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check ability to display both prices for buckaroo fee in shopping cart
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displayCartBothPrices($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplayCart($store);

        return $configValue == DisplayType::DISPLAY_TYPE_BOTH;
    }

    /**
     * Check ability to display prices including tax for buckaroo fee in backend sales
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displaySalesIncludeTaxPrice($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplaySales($store);

        return $configValue == DisplayType::DISPLAY_TYPE_BOTH ||
            $configValue == DisplayType::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check ability to display prices excluding tax for buckaroo fee in backend sales
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displaySalesExcludeTaxPrice($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplaySales($store);

        return $configValue == DisplayType::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check ability to display both prices for buckaroo fee in backend sales
     *
     * @param  \Magento\Store\Model\Store|int|null $store
     * @return bool
     */
    public function displaySalesBothPrices($store = null)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $configValue = $this->configProviderBuckarooFee->getPriceDisplaySales($store);

        return $configValue == DisplayType::DISPLAY_TYPE_BOTH;
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
    protected function addTotalToTotals(&$totals, $code, $value, $baseValue, $label, $block_name = false, $transaction_id = false)
    {
        if ($value == 0 && $baseValue == 0) {
            return;
        }
        $total = ['code' => $code, 'value' => $value, 'base_value' => $baseValue, 'label' => $label];
        if ($block_name) {
            $total['block_name'] = $block_name;
        }
        if ($transaction_id) {
            $total['transaction_id'] = $transaction_id;
        }
        $totals[] = $total;
    }
}
