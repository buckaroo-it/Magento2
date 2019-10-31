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

use Magento\Framework\Validator\Exception;
use Magento\Sales\Model\Order\Payment;

class Giropay extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_giropay';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'giropay';

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
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['customer_bic'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_bic',
                $data['additional_data']['customer_bic']
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'giropay',
            'Action'           => 'Pay',
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $payment->getAdditionalInformation('customer_bic'),
                    'Name' => 'bic',
                ],
            ],
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
            'Name'    => 'giropay',
            'Action'  => 'Refund',
            'Version' => 2,
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
     * Validate the extra input fields.
     *
     * @return $this
     * @throws Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this;
        }

        $customerBicNumber = $paymentInfo->getAdditionalInformation('customer_bic');

        if (!preg_match(static::BIC_NUMBER_REGEX, $customerBicNumber)) {
            throw new Exception(__('Please enter a valid BIC number'));
        }

        return $this;
    }
}
