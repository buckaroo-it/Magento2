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

use Magento\Framework\DataObject;

class IdealProcessing extends AbstractMethod
{
    /**
     * Payment Code
     */
    public const PAYMENT_METHOD_CODE = 'buckaroo_magento2_idealprocessing';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'idealprocessing';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_canRefund               = false;

    /**
     * {@inheritdoc}
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'idealprocessing',
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
            'Version'          => 2,
            'RequestParameter' => [],
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        $transactionBuilder->setCustomVars(['ContinueOnIncomplete' => 'RedirectToHTML']);

        return $transactionBuilder;
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
     * Validate additional data.
     * Removed issuer validation logic.
     *
     * {@inheritdoc}
     */
    public function validateAdditionalData()
    {
        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $this->buckarooPaymentMethodCode;
    }

    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $orderId = $quote ? $quote->getReservedOrderId() : null;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $paymentGroupTransaction = $objectManager->get(\Buckaroo\Magento2\Helper\PaymentGroupTransaction::class);

        if ($paymentGroupTransaction->getAlreadyPaid($orderId) > 0) {
            return false;
        }

        return true;
    }
}
