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

namespace Buckaroo\Magento2\Helper;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\Store;

class PaymentFee extends AbstractHelper
{
    /**
     * @var bool
     */
    public $buckarooFee = false;

    /**
     * @var bool
     */
    public $buckarooFeeTax = false;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var BuckarooFee
     */
    protected $configProviderBuckarooFee;

    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var Collection
     */
    protected $giftcardCollection;

    /**
     * @param Context $context
     * @param Account $configProviderAccount
     * @param BuckarooFee $configProviderBuckarooFee
     * @param Factory $configProviderMethodFactory
     * @param PaymentGroupTransaction $groupTransaction
     * @param Collection $giftcardCollection
     */
    public function __construct(
        Context $context,
        Account $configProviderAccount,
        BuckarooFee $configProviderBuckarooFee,
        Factory $configProviderMethodFactory,
        PaymentGroupTransaction $groupTransaction,
        Collection $giftcardCollection
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
     * @param DataObject $dataObject
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @throws Exception
     */
    public function getTotals($dataObject)
    {
        $totals = [];
        $displayBothPrices = false;
        $displayIncludeTaxPrice = false;


        if ($dataObject instanceof Order
            || $dataObject instanceof Invoice
            || $dataObject instanceof Creditmemo
        ) {
            $displayBothPrices = $this->displaySalesBothPrices();
            $displayIncludeTaxPrice = $this->displaySalesIncludeTaxPrice();
        } elseif ($dataObject instanceof Total) {
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
                    $label . __(' (Excl. Tax)'),
                    false,
                    true
                );
            }
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $this->addTotalToTotals(
                $totals,
                ($dataObject instanceof Creditmemo) ? 'buckaroo_fee' : 'buckaroo_fee_incl',
                $dataObject->getBuckarooFee() + $dataObject->getBuckarooFeeTaxAmount(),
                $dataObject->getBaseBuckarooFee() + $dataObject->getBuckarooFeeBaseTaxAmount(),
                $label . __(' (Incl. Tax)'),
                ($dataObject instanceof Creditmemo) ? 'buckaroo_fee' : false,
                false,
                [
                    'incl_tax'     => true,
                    'fee_with_tax' => $dataObject->getBuckarooFee() + $dataObject->getBuckarooFeeTaxAmount()
                ]
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
                ($dataObject instanceof Creditmemo) ? 'buckaroo_fee' : false,
                false,
                [
                    'incl_tax'     => false,
                    'fee_with_tax' => $dataObject->getBuckarooFee() + $dataObject->getBuckarooFeeTaxAmount()
                ]
            );
        }

        $this->addAlreadyPayedTotals($dataObject, $totals);

        //Set public object data
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->buckarooFee = $dataObject->getBuckarooFee();
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $this->buckarooFeeTax = $dataObject->getBuckarooFeeTaxAmount();
        return $totals;
    }

    /**
     * Check ability to display both prices for buckaroo fee in backend sales
     *
     * @param Store|int|null $store
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
     * Check ability to display prices including tax for buckaroo fee in backend sales
     *
     * @param Store|int|null $store
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
     * Check ability to display both prices for buckaroo fee in shopping cart
     *
     * @param Store|int|null $store
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
     * Check ability to display prices including tax for buckaroo fee in shopping cart
     *
     * @param Store|int|null $store
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
     * Return the correct label for the payment method
     *
     * @param Order|Invoice|Creditmemo|Total|DataObject|string|bool $dataObject
     * @return string
     * @throws Exception
     */
    public function getBuckarooPaymentFeeLabel($dataObject)
    {
        $method = false;
        $label = false;

        /**
         * Parse data object for payment method
         */
        if ($dataObject instanceof Order) {
            $method = $dataObject->getPayment()->getMethod();
        } elseif ($dataObject instanceof Invoice
            || $dataObject instanceof Creditmemo
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
     * Add total into array totals
     *
     * @param array $totals
     * @param string $code
     * @param float $value
     * @param float $baseValue
     * @param string $label
     * @return void
     */
    protected function addTotalToTotals(
        &$totals,
        $code,
        $value,
        $baseValue,
        $label,
        $blockName = false,
        $transactionId = false,
        $extraInfo = []
    ) {
        if ($value == 0 && $baseValue == 0) {
            return;
        }
        $total = [
            'code'       => $code,
            'value'      => $value,
            'base_value' => $baseValue,
            'label'      => $label,
            'extra_info' => $extraInfo
        ];
        if ($blockName) {
            $total['block_name'] = $blockName;
        }
        if ($transactionId) {
            $total['transaction_id'] = $transactionId;
        }
        $totals[] = $total;
    }

    /**
     * Get Buckaroo fee
     *
     * @return mixed
     */
    public function getBuckarooFee()
    {
        return $this->buckarooFee;
    }

    /**
     * Add already paid to totals
     *
     * @param DataObject $dataObject
     * @param array $totals
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addAlreadyPayedTotals($dataObject, &$totals)
    {
        $orderId = $this->getOrderIncrementId($dataObject);
        $alreadyPayed = $this->groupTransaction->getAlreadyPaid($orderId);

        if (!$dataObject instanceof Creditmemo && $alreadyPayed > 0) {
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

        if ($orderId !== null && $alreadyPayed > 0) {
            $requestParams = $this->_request->getParams();
            $items = $this->groupTransaction->getGroupTransactionItems($orderId);
            $giftcards = [];

            if (isset($requestParams['creditmemo']['buckaroo_already_paid'])) {
                foreach ($requestParams['creditmemo']['buckaroo_already_paid'] as $giftcardKey => $value) {
                    $transaction = explode('|', $giftcardKey);
                    $giftcards[$transaction[1]] = $value;
                }
            }
            foreach ($items as $giftcard) {
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
                    if (array_key_exists($foundGiftcard['servicecode'], $giftcards)
                        && floatval($giftcards[$foundGiftcard['servicecode']]) <= $residual
                    ) {
                        $amountValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                        $amountBaseValue = floatval($giftcards[$foundGiftcard['servicecode']]);
                    } else {
                        $amountBaseValue = $residual;
                        $amountValue = $residual;
                    }
                } else {
                    if (!empty(floatval($refundedAlreadyPaidSaved))
                            && floatval($refundedAlreadyPaidSaved) === floatval($amountValue)
                    ) {
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
     * @return string|null
     */
    public function getOrderIncrementId($dataObject)
    {
        if ($dataObject instanceof Order) {
            return $dataObject->getIncrementId();
        }
        if ($dataObject instanceof Invoice
            || $dataObject instanceof Creditmemo
        ) {
            return $dataObject->getOrder()->getIncrementId();
        }

        return null;
    }

    /**
     * Get buckaroo fee tax
     *
     * @return mixed
     */
    public function getBuckarooFeeTax()
    {
        return $this->buckarooFeeTax;
    }

    /**
     * Add payment fee to total
     *
     * @param DataObject $dataObject
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
     * @param Store|int|null $store
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
     * @param Store|int|null $store
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
     * Check ability to display prices excluding tax for buckaroo fee in shopping cart
     *
     * @param Store|int|null $store
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
     * Check ability to display prices excluding tax for buckaroo fee in backend sales
     *
     * @param Store|int|null $store
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
}
