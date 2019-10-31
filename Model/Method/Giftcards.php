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

namespace TIG\Buckaroo\Model\Method;

use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;

class Giftcards extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_giftcards';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'giftcards';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isGateway               = true;

    /**
     * @var bool
     */
    protected $_canOrder                = true;

    /**
     * @var bool
     */
    protected $_canAuthorize            = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial       = true;

    /**
     * @var bool
     */
    protected $_canRefund               = false;

    /**
     * @var bool
     */
    protected $_canVoid                 = true;

    /**
     * @var bool
     */
    protected $_canUseInternal          = false;

    /**
     * @var bool
     */
    protected $_canUseCheckout          = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = false;

    /**
     * Check capture availability
     *
     * @return bool
     * @api
     */
    public function canCapture()
    {
        if ($this->getConfigData('payment_action') == 'order') {
            return false;
        }

        return $this->_canCapture;
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        /**
         * If there are no giftcards chosen, we can't be available
         */
        /**
         * @var \TIG\Buckaroo\Model\ConfigProvider\Method\Giftcards $ccConfig
         */
        $gcConfig = $this->configProviderMethodFactory->get('giftcards');
        if (null === $gcConfig->getAllowedGiftcards()) {
            return false;
        }
        /**
         * Return the regular isAvailable result
         */
        return parent::isAvailable($quote);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $availableCards = $this->_scopeConfig->getValue(
            GiftcardsConfig::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            ScopeInterface::SCOPE_STORE,
            $payment->getOrder()->getStore()
        );
        $availableCards .= ',ideal';

        $customVars = [
            'ServicesSelectableByClient' => $availableCards,
            'ContinueOnIncomplete' => 'RedirectToHTML',
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setCustomVars($customVars)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'giftcards',
            'Action'           => 'Authorize',
            'Version'          => 1,
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $capturePartial = false;
        $order = $payment->getOrder();
        $totalOrder = $order->getBaseGrandTotal();
        $numberOfInvoices = $order->getInvoiceCollection()->count();

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        } else {
            //partial capture
            $capturePartial = true;
        }

        $services = [
            'Name'             => 'giftcards',
            'Action'           => 'Capture',
            'Version'          => 1,
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setChannel('CallCenter')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(
                    self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
                )
            );

        // Partial Capture Settings
        if ($capturePartial) {

            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $transactionBuilder->setAmount($currentInvoiceTotal)
                ->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
                ->setCurrency($this->payment->getOrder()->getOrderCurrencyCode())
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }
}
