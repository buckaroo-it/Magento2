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

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Store\Api\Data\StoreInterface;

class PaymentFee extends AbstractHelper
{
    /** @var AccountConfigProvider */
    protected $configProviderAccount;

    /** @var BuckarooFeeConfigProvider */
    protected $configProviderBuckarooFee;

    /** @var MethodConfigProviderFactory */
    protected $configProviderMethodFactory;

    /** @var bool|float */
    public $buckarooFee = false;

    /** @var bool|float */
    public $buckarooFeeTax = false;

    /** @var PaymentGroupTransaction */
    protected $groupTransaction;

    /** @var GiftcardCollection */
    protected $giftcardCollection;

    /**
     * @param Context                     $context
     * @param AccountConfigProvider       $configProviderAccount
     * @param BuckarooFeeConfigProvider   $configProviderBuckarooFee
     * @param MethodConfigProviderFactory $configProviderMethodFactory
     * @param PaymentGroupTransaction     $groupTransaction
     * @param GiftcardCollection          $giftcardCollection
     */
    public function __construct(
        Context $context,
        AccountConfigProvider $configProviderAccount,
        BuckarooFeeConfigProvider $configProviderBuckarooFee,
        MethodConfigProviderFactory $configProviderMethodFactory,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection
    ) {
        parent::__construct($context);
        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
    }

    /**
     * Retrieve totals array based on the data object.
     *
     * @param DataObject $dataObject
     * @return array
     */
    public function getTotals($dataObject)
    {
        $totals = [];
        $store = $this->getStoreFromDataObject($dataObject);

        $taxClassId = $this->configProviderBuckarooFee->getBuckarooFeeTaxClass($store);
        $isIncludingTax = $this->isFeeDisplayTypeIncludingTax($taxClassId);

        $label = $this->getBuckarooPaymentFeeLabel();
        $fee = $dataObject->getBuckarooFee();
        $feeTaxAmount = $dataObject->getBuckarooFeeTaxAmount();
        $baseFee = $dataObject->getBaseBuckarooFee();
        $baseFeeTaxAmount = $dataObject->getBuckarooFeeBaseTaxAmount();

        // Add the fee total line depending on the display type
        if ($isIncludingTax) {
            $this->addTotalToTotals(
                $totals,
                'buckaroo_fee_incl',
                $fee + $feeTaxAmount,
                $baseFee + $baseFeeTaxAmount,
                $label . __(' (Incl. Tax)')
            );
        } else {
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

    /**
     * Extract the store from the data object.
     *
     * @param DataObject $dataObject
     * @return StoreInterface|null
     */
    protected function getStoreFromDataObject($dataObject)
    {
        if ($dataObject instanceof Order) {
            return $dataObject->getStore();
        } elseif ($dataObject instanceof Invoice || $dataObject instanceof Creditmemo) {
            return $dataObject->getOrder()->getStore();
        }

        return null;
    }

    /**
     * Determine if the fee display type is set to "Including Tax".
     *
     * @param mixed $displayType
     * @return bool
     */
    protected function isFeeDisplayTypeIncludingTax($displayType)
    {
        return (int)$displayType === DisplayType::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Add "already paid" totals for giftcards or vouchers if applicable.
     *
     * @param DataObject $dataObject
     * @param array &$totals
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addAlreadyPayedTotals($dataObject, array &$totals)
    {
        $orderId = $this->getOrderIncrementId($dataObject);
        $alreadyPayed = $this->groupTransaction->getAlreadyPaid($orderId);

        // For orders (not creditmemos), if there's an already paid amount, adjust totals accordingly
        if (!$dataObject instanceof Creditmemo && $alreadyPayed > 0) {
            // Remove the fee line if previously added
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

        // Handling creditmemo cases
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

                $refundedAlreadyPaidSaved = $giftcard->getRefundedAmount() ?? 0.0;
                $amountValue = (float)$giftcard['amount'];
                $amountBaseValue = $amountValue;

                // Handle partially refundable giftcards
                if (!empty($foundGiftcard['is_partial_refundable'])) {
                    $residual = $amountValue - (float)$refundedAlreadyPaidSaved;

                    if (array_key_exists($foundGiftcard['servicecode'], $giftcards) &&
                        (float)$giftcards[$foundGiftcard['servicecode']] <= $residual
                    ) {
                        $amountValue = (float)$giftcards[$foundGiftcard['servicecode']];
                        $amountBaseValue = $amountValue;
                    } else {
                        $amountValue = $residual;
                        $amountBaseValue = $residual;
                    }
                } else {
                    // Non-partially refundable logic
                    if ((float)$refundedAlreadyPaidSaved === $amountValue) {
                        $amountValue = 0;
                        $amountBaseValue = 0;
                    } elseif (is_array($foundGiftcard) &&
                        array_key_exists($foundGiftcard['servicecode'], $giftcards) &&
                        empty((float)$giftcards[$foundGiftcard['servicecode']])
                    ) {
                        $amountValue = 0;
                        $amountBaseValue = 0;
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
     * Get order increment ID from a data object (Order/Invoice/Creditmemo).
     *
     * @param mixed $dataObject
     * @return string|null
     */
    public function getOrderIncrementId($dataObject)
    {
        if ($dataObject instanceof Order) {
            return $dataObject->getIncrementId();
        }
        if ($dataObject instanceof Invoice || $dataObject instanceof Creditmemo) {
            return $dataObject->getOrder()->getIncrementId();
        }
        return null;
    }

    /**
     * Get the Buckaroo fee amount.
     *
     * @return mixed
     */
    public function getBuckarooFee()
    {
        return $this->buckarooFee;
    }

    /**
     * Get the Buckaroo fee tax amount.
     *
     * @return mixed
     */
    public function getBuckarooFeeTax()
    {
        return $this->buckarooFeeTax;
    }

    /**
     * Retrieve the correct label for the Buckaroo payment fee.
     *
     * @return string
     */
    public function getBuckarooPaymentFeeLabel()
    {
        return __('Payment Fee');
    }

    /**
     * Extract the payment method code from order, invoice, creditmemo, or direct string.
     *
     * @param mixed $dataObject
     * @return string|false
     */
    protected function extractPaymentMethodFromDataObject($dataObject)
    {
        if ($dataObject instanceof Order) {
            return $dataObject->getPayment()->getMethod();
        } elseif ($dataObject instanceof Invoice || $dataObject instanceof Creditmemo) {
            return $dataObject->getOrder()->getPayment()->getMethod();
        } elseif (is_string($dataObject)) {
            return $dataObject;
        }

        return false;
    }

    /**
     * Add a total entry into the provided totals array.
     *
     * @param array  &$totals
     * @param string $code
     * @param float  $value
     * @param float  $baseValue
     * @param string $label
     * @param string $blockName
     * @param string $transactionId
     * @param array  $extraInfo
     * @return void
     */
    protected function addTotalToTotals(
        array &$totals,
        $code,
        $value,
        $baseValue,
        $label,
        $blockName = false,
        $transactionId = false,
        array $extraInfo = []
    ) {
        // Only add totals if values are non-zero
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
}
