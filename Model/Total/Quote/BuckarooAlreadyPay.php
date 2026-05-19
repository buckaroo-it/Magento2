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

namespace Buckaroo\Magento2\Model\Total\Quote;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection;
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
     * @param  Quote $quote
     * @param  Total $total
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        $orderId = $quote->getReservedOrderId();

        $customTitle = [];
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
            } catch (\Exception $e) {
            }
        }

        return [
            'code'  => $this->getCode(),
            'title' => $customTitle ? __(json_encode($customTitle)) : $this->getLabel(),
            'value' => -$this->groupTransaction->getAlreadyPaid($orderId),
        ];
    }

    /**
     * Get Buckaroo label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Paid with Giftcard');
    }
}
