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

namespace Buckaroo\Magento2\Block;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::info/payment_method.phtml';
    protected $groupTransaction;
    protected $giftcardCollection;

    /**
     * @param \Magento\Framework\View\Element\Template\Context     $context
     * @param array                                                $data
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard $configProvider
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        PaymentGroupTransaction $groupTransaction,
        GiftcardCollection $giftcardCollection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
    }

    public function getGiftCards()
    {
        $result = [];

        if ($this->getInfo()->getOrder() && $this->getInfo()->getOrder()->getIncrementId()) {
            $items = $this->groupTransaction->getGroupTransactionItems($this->getInfo()->getOrder()->getIncrementId());
            foreach ($items as $key => $giftcard) {
                if ($foundGiftcard = $this->giftcardCollection
                    ->getItemByColumnValue('servicecode', $giftcard['servicecode'])
                ) {
                    $result[] = [
                        'code' => $giftcard['servicecode'],
                        'label' => $foundGiftcard['label'],
                    ];
                }
            }
        }

        return $result;
    }

    public function getPayPerEmailMethod()
    {
        $payment = $this->getInfo()->getOrder()->getPayment();
        if ($servicecode = $payment->getAdditionalInformation('isPayPerEmail')) {
            return [
                    'label' => __('Buckaroo PayPerEmail'),
                ];
        }
        return false;
    }
}
