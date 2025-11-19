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

namespace Buckaroo\Magento2\Model\Total\Quote;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class BuckarooAlreadyPay extends AbstractTotal
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var Collection
     */
    protected $giftcardCollection;

    /**
     * Constructor
     *
     * @param PriceCurrencyInterface  $priceCurrency
     * @param PaymentGroupTransaction $groupTransaction
     * @param Collection              $giftcardCollection
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        PaymentGroupTransaction $groupTransaction,
        Collection $giftcardCollection
    ) {
        $this->setCode('buckaroo_already_paid');
        $this->priceCurrency      = $priceCurrency;
        $this->groupTransaction   = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
    }

    /**
     * Add buckaroo fee information to address
     *
     * @param Quote $quote
     * @param Total $total
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        $orderId = $quote->getReservedOrderId();

        $customTitle = [];
        $alreadyPaid = 0;

        if ($orderId) {
            try {
                $items = $this->groupTransaction->getGroupTransactionItemsNotRefunded($orderId);

                foreach ($items as $giftcard) {
                    $foundGiftcard = $this->giftcardCollection->getItemByColumnValue(
                        'servicecode',
                        $giftcard['servicecode']
                    );

                    if ($foundGiftcard !== null || $giftcard['servicecode'] === 'buckaroovoucher') {
                        if ($giftcard['servicecode'] === 'buckaroovoucher') {
                            $label = __('Voucher');
                        } else {
                            $label = $foundGiftcard['label'];
                        }

                        $customTitle[] = [
                            'label'          => __('Paid with') . ' ' . $label,
                            'amount'         => -$giftcard['amount'],
                            'servicecode'    => $giftcard['servicecode'],
                            'serviceamount'  => $giftcard['amount'],
                            'transaction_id' => $giftcard['transaction_id'],
                        ];
                    }
                }

                $alreadyPaid = $this->groupTransaction->getAlreadyPaid($orderId);

                // Fallback: If no group transactions exist, check if this is a single giftcard payment
                if (empty($customTitle)) {
                    $singleGiftcardInfo = $this->getSingleGiftcardPaymentInfo($quote);
                    if ($singleGiftcardInfo) {
                        $customTitle[] = $singleGiftcardInfo;
                        $alreadyPaid = $singleGiftcardInfo['serviceamount'];
                    }
                }
            } catch (\Exception $e) {
            }
        }

        return [
            'code'  => $this->getCode(),
            'title' => $customTitle ? __(json_encode($customTitle)) : $this->getLabel(),
            'value' => $alreadyPaid,
        ];
    }

    /**
     * Get single giftcard payment info for quotes that don't have group transactions yet
     *
     * @param Quote $quote
     * @return array|null
     */
    private function getSingleGiftcardPaymentInfo(Quote $quote): ?array
    {
        $payment = $quote->getPayment();
        if (!$payment) {
            return null;
        }

        // Check if payment method is giftcards
        if ($payment->getMethod() !== 'buckaroo_magento2_giftcards') {
            return null;
        }

        // Try to get info from additional_information (set during checkout)
        $rawDetailsInfo = $payment->getAdditionalInformation('raw_details_info');
        if (!is_array($rawDetailsInfo) || empty($rawDetailsInfo)) {
            return null;
        }

        $firstTransaction = reset($rawDetailsInfo);
        $servicecode = $firstTransaction['brq_transaction_method'] ?? null;
        $amount = $firstTransaction['brq_amount'] ?? null;

        if (!$servicecode || !$amount) {
            return null;
        }

        // Try to find the giftcard in the collection
        $foundGiftcard = $this->giftcardCollection->getItemByColumnValue('servicecode', $servicecode);

        if ($foundGiftcard !== null) {
            $label = $foundGiftcard['label'];
        } elseif ($servicecode === 'buckaroovoucher') {
            $label = __('Voucher');
        } else {
            $label = ucfirst($servicecode) . ' Giftcard';
        }

        return [
            'label'          => __('Paid with') . ' ' . $label,
            'amount'         => -(float)$amount,
            'servicecode'    => $servicecode,
            'serviceamount'  => (float)$amount,
            'transaction_id' => $firstTransaction['brq_transactions'] ?? null,
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return Phrase
     */
    public function getLabel()
    {
        return __('Paid with Giftcard');
    }
}
