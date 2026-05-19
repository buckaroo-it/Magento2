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

namespace Buckaroo\Magento2\Model\Method;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class Ideal extends AbstractMethod
{
    /**
     * Payment Code
     */
    public const PAYMENT_METHOD_CODE = 'buckaroo_magento2_ideal';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'ideal';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $payment = $this->getInfoInstance();
        $quote = $payment->getQuote();

        if ($quote) {
            $shippingCost = $quote->getShippingAddress()->getShippingInclTax();
            $this->getInfoInstance()->setAdditionalInformation('shippingCost', $shippingCost);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');
        if ($this->isFastCheckout($payment)) {
            $services = [
                'Name'             => 'ideal',
                'Action'           => $this->getPayRemainder($payment, $transactionBuilder, 'PayFastCheckout'),
                'Version'          => 2,
                'RequestParameter' => $this->getIdealFastCheckoutOrderRequestParameters($payment),
            ];

            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $transactionBuilder->setOrder($payment->getOrder())
                ->setServices($services)
                ->setMethod('TransactionRequest');
        } else {
            $services = [
                'Name'             => 'ideal',
                'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
                'Version'          => 2,
                'RequestParameter' => [],
            ];
            /**
             * @noinspection PhpUndefinedMethodInspection
             */
            $transactionBuilder->setOrder($payment->getOrder())
                ->setServices($services)
                ->setMethod('TransactionRequest');

            $transactionBuilder->setCustomVars(['ContinueOnIncomplete' => 'RedirectToHTML']);
        }

        return $transactionBuilder;
    }

    /**
     * Get request parameters for iDEAL Fast Checkout order.
     * Remains unchanged.
     *
     * @param        $payment
     * @return array
     */
    private function getIdealFastCheckoutOrderRequestParameters($payment): array
    {
        $parameters = [];

        if ($this->isFastCheckout($payment) && $payment->getAdditionalInformation('shippingCost')) {
            $parameters = [[
                '_'    => $payment->getAdditionalInformation('shippingCost'),
                'Name' => 'shippingCost',
            ]];
        }

        return $parameters;
    }

    /**
     */
    protected function getRefundTransactionBuilderVersion()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
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

    /**
     * Check if the order request parameters indicate a fast checkout.
     *
     * @param       $payment
     * @return bool
     */
    private function isFastCheckout($payment): bool
    {
        // This check relies on 'issuer' potentially being 'fastcheckout',
        // ensure this value is still correctly set in assignData if needed for Fast Checkout flow.
        // If Fast Checkout also changes, this might need adjustment. Assuming it remains for now.
        return $payment->getAdditionalInformation('issuer') === 'fastcheckout';
    }

    /**
     * Validate additional data.
     * Removed issuer validation for standard iDEAL.
     *
     * @param                                                  $payment
     * @return $this
     */
    public function validateAdditionalData($payment)
    {

        if ($this->isFastCheckout($payment)) {
            return $this;
        }

        return $this;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $this->buckarooPaymentMethodCode;
    }
}
