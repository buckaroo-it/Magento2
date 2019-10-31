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

use TIG\Buckaroo\Model\ConfigProvider\Method\Kbc as KbcConfig;

class Kbc extends AbstractMethod
{
    /** Payment Code*/
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_kbc';

    /** @var string */
    public $buckarooPaymentMethodCode = 'kbc';

    // @codingStandardsIgnoreStart
    /** @var string */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /** @var bool */
    protected $_isGateway               = true;

    /** @var bool */
    protected $_canOrder                = true;

    /** @var bool */
    protected $_canAuthorize            = false;

    /** @var bool */
    protected $_canCapture              = false;

    /** @var bool */
    protected $_canCapturePartial       = false;

    /** @var bool */
    protected $_canRefund               = true;

    /** @var bool */
    protected $_canVoid                 = true;

    /** @var bool */
    protected $_canUseInternal          = true;

    /** @var bool */
    protected $_canUseCheckout          = true;

    /** @var bool */
    protected $_canRefundInvoicePartial = true;
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'KBCPaymentButton',
            'Action'           => 'Pay',
            'Version'          => 1
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
    public function getRefundTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $services = [
            'Name'    => 'KBCPaymentButton',
            'Action'  => 'Refund',
            'Version' => 1,
        ];

        $requestParams = $this->addExtraFields($this->_code);
        $services = array_merge($services, $requestParams);

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            );

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }
}
