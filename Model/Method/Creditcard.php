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

class Creditcard extends AbstractMethod
{
    /**
     * Payment Code
     */
    public const PAYMENT_METHOD_CODE = 'buckaroo_magento2_creditcard';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'creditcard';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_canAuthorize            = true;

    /**
     * @var bool
     */
    protected $_canCapture              = true;

    /**
     * @var string
     */
    protected $_infoBlockType = 'Buckaroo\Magento2\Block\Info\Creditcard';

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction = false;

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['card_type'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'card_type',
                $data['additional_data']['card_type']
            );
        }

        return $this;
    }

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
    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        // Check allowed creditcards
        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard $ccConfig */
        $ccConfig = $this->configProviderMethodFactory->get('creditcard');
        if ($ccConfig->getAllowedCreditcards() === null) {
            return false;
        }

        // Let all generic checks run (amount, IP, currency, spam, etc.)
        if (!parent::isAvailable($quote)) {
            return false;
        }

        // Block if order already has a (partial) payment
        if ($this->isOrderPartiallyPaid($quote)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => $payment->getAdditionalInformation('card_type'),
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
            'Version'          => 1,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
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

        $services = [
            'Name'             => $payment->getAdditionalInformation('card_type'),
            'Action'           => 'Capture',
            'Version'          => 1,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setChannel('CallCenter')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(
                    self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY
                )
            );

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => $payment->getAdditionalInformation('card_type'),
            'Action'           => 'Authorize',
            'Version'          => 1,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $payment->getAdditionalInformation('card_type');
    }
}
