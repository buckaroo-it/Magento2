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

use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Buckaroo\Magento2\Model\Config\Source\TaxClass\Calculation;
use Buckaroo\Magento2\Model\ConfigProvider\Account as ConfigProviderAccount;
// use Buckaroo\Magento2\Model\ConfigProvider\BuckarooAlreadyPay as ConfigProviderBuckarooAlreadyPay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;

class BuckarooAlreadyPay extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /** @var ConfigProviderAccount */
    protected $configProviderAccount;

    /** @var ConfigProviderBuckarooAlreadyPay */
    protected $configProviderBuckarooAlreadyPay;

    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var PriceCurrencyInterface
     */
    public $priceCurrency;

    /**
     * @var Data
     */
    public $catalogHelper;
        // ConfigProviderBuckarooAlreadyPay $configProviderBuckarooAlreadyPay,

    /**
     * @param ConfigProviderAccount     $configProviderAccount
     * @param ConfigProviderBuckarooAlreadyPay $configProviderBuckarooAlreadyPay
     * @param Factory                   $configProviderMethodFactory
     * @param PriceCurrencyInterface    $priceCurrency
     * @param Data                      $catalogHelper
     */
    public function __construct(
        ConfigProviderAccount $configProviderAccount,
        Factory $configProviderMethodFactory,
        PriceCurrencyInterface $priceCurrency,
        Data $catalogHelper,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->setCode('buckaroo_already_paid');

        $this->configProviderAccount = $configProviderAccount;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceCurrency = $priceCurrency;
        $this->catalogHelper = $catalogHelper;

        $this->_checkoutSession       = $checkoutSession;
    }

    /**
     * Collect grand total address amount
     *
     * @param  \Magento\Quote\Model\Quote                          $quote
     * @param  \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param  \Magento\Quote\Model\Quote\Address\Total            $total
     * @return $this
     *
     * @throws \LogicException
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooAlreadyPay(0);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooAlreadyPay(0);

        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        $paymentMethod = $quote->getPayment()->getMethod();
        if (!$paymentMethod || strpos($paymentMethod, 'buckaroo_magento2_') !== 0) {
            return $this;
        }

        $methodInstance = $quote->getPayment()->getMethodInstance();
        if (!$methodInstance instanceof \Buckaroo\Magento2\Model\Method\AbstractMethod) {
            return $this;
        }

        $orderId = $quote->getReservedOrderId();
        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();

        if (!isset($alreadyPaid[$orderId]) || $alreadyPaid[$orderId] < 0.01) {
            return $this;
        }

        $baseAlreadyPaid = $alreadyPaid[$orderId];
        $alreadyPaid = $this->priceCurrency->convert($baseAlreadyPaid, $quote->getStore());

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBuckarooAlreadyPaid($alreadyPaid);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $quote->setBaseBuckarooAlreadyPaid($baseAlreadyPaid);
        $quote->save($quote);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBuckarooAlreadyPaid($alreadyPaid);
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseBuckarooAlreadyPaid($baseAlreadyPaid);

        $baseGrandTotal = $total->getBaseGrandTotal() - $baseAlreadyPaid;
        $grandTotal = $total->getGrandTotal() - $alreadyPaid;
        if($paymentMethod == 'buckaroo_magento2_giftcards'){
            $cartTotals = $quote->getTotals();
            $grand_total = $cartTotals['grand_total']->getData();
            if(($grand_total['value'] - $baseAlreadyPaid) < 0.001){
               $baseGrandTotal = $baseAlreadyPaid  + 0.001; 
               $grandTotal = $grandTotal + 0.001;
            }
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setBaseGrandTotal($baseGrandTotal);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $total->setGrandTotal($grandTotal);

        return $this;
    }

    /**
     * Add buckaroo fee information to address
     *
     * @param  \Magento\Quote\Model\Quote               $quote
     * @param  \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        $orderId = $quote->getReservedOrderId();
        $alreadyPaid = $this->_checkoutSession->getBuckarooAlreadyPaid();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $totals = [
            'code' => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => isset($alreadyPaid[$orderId]) && $alreadyPaid[$orderId] > 0 ? - $alreadyPaid[$orderId] : false
        ];
        return $totals;
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
