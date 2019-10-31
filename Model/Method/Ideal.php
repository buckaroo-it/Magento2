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

use TIG\Buckaroo\Model\ConfigProvider\Method\Ideal as IdealConfig;

class Ideal extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_ideal';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'ideal';

    // @codingStandardsIgnoreStart
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

        if (isset($data['additional_data']['buckaroo_skip_validation'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'buckaroo_skip_validation',
                $data['additional_data']['buckaroo_skip_validation']
            );
        }

        if (isset($data['additional_data']['issuer'])) {
            $this->getInfoInstance()->setAdditionalInformation('issuer', $data['additional_data']['issuer']);
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
            'Name'             => 'ideal',
            'Action'           => 'Pay',
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $payment->getAdditionalInformation('issuer'),
                    'Name' => 'issuer',
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
            'Name'    => 'ideal',
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
     * Validate that we received a valid issuer ID.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        parent::validate();

        /**
         * @var IdealConfig $config
         */
        $config = $this->objectManager->get(IdealConfig::class);

        $paymentInfo = $this->getInfoInstance();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this;
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        $valid = false;
        foreach ($config->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please select a issuer from the list'));
        }

        return $this;
    }
}
