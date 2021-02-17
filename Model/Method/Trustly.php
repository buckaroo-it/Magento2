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

class Trustly extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_trustly';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'trustly';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

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
    protected $_canAuthorize            = false;

    /**
     * @var bool
     */
    protected $_canCapture              = false;

    /**
     * @var bool
     */
    protected $_canCapturePartial       = false;

    /**
     * @var bool
     */
    protected $_canRefund               = true;

    /**
     * @var bool
     */
    protected $_canVoid                 = true;

    /**
     * @var bool
     */
    protected $_canUseInternal          = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout          = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    // @codingStandardsIgnoreEnd

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $billingAddress = $payment->getOrder()->getBillingAddress();

        $services = [
            'Name'             => 'Trustly',
            'Action'           => 'Pay',
            'Version'          => 1,
            'RequestParameter' => [
                [
                    '_' => $billingAddress->getCountryId(),
                    'Name' => 'CustomerCountryCode',
                ],
                [
                    '_' => $billingAddress->getFirstname(),
                    'Name' => 'CustomerFirstName',
                ],
                [
                    '_' => $billingAddress->getLastName(),
                    'Name' => 'CustomerLastName',
                ],
            ],
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * @todo when buckaroo changes the push / response order this can be removed
         */
        $payment->setAdditionalInformation(
            'skip_push',
            1
        );

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
            'Name'    => 'trustly',
            'Action'  => 'Refund',
            'Version' => 1,
        ];

        $requestParams = $this->addExtraFields($this->_code);
        $services = array_merge($services, $requestParams);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey(
                $payment->getAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY)
            )
            ->setChannel('CallCenter');

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
     * Failure message from failed Trustly Transactions
     *
     * {@inheritdoc}
     */
    protected function getFailureMessageFromMethod($transactionResponse)
    {
        $methodMessage = '';
        $responseCode = $transactionResponse->Status->Code->Code;
        if ($responseCode == 491) {
            if (!empty($transactionResponse->RequestErrors->ParameterError->Name)
                &&
                ($transactionResponse->RequestErrors->ParameterError->Name == 'CustomerCountryCode')
                &&
                !empty($transactionResponse->RequestErrors->ParameterError->Error)
                &&
                ($transactionResponse->RequestErrors->ParameterError->Error == 'ParameterInvalid')
                &&
                !empty($transactionResponse->RequestErrors->ParameterError->_)
            ) {
                $methodMessage = $transactionResponse->RequestErrors->ParameterError->_;
            }
        }

        return $methodMessage;
    }
}
