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

use Buckaroo\Magento2\Exception;

class Applepay extends AbstractMethod
{
    /** Payment Code */
    public const PAYMENT_METHOD_CODE = 'buckaroo_magento2_applepay';

    /** @var string */
    public $buckarooPaymentMethodCode = 'applepay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /**
     * Additional data keys
     */
    private const TRANSACTION_DATA_KEY = 'applepayTransaction';
    private const BILLING_CONTACT_KEY = 'billingContact';

    /**
     * Assign payment data
     *
     * @param \Magento\Framework\DataObject $data
     * @return $this
     * @throws Exception
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data'][self::TRANSACTION_DATA_KEY])) {
            $transactionData = $data['additional_data'][self::TRANSACTION_DATA_KEY];
            $this->validateApplePayTransactionData($transactionData);

            $applepayEncoded = base64_encode($transactionData);
            $this->getInfoInstance()->setAdditionalInformation(self::TRANSACTION_DATA_KEY, $applepayEncoded);
        }

        // Handle billing contact
        if (!empty($data['additional_data'][self::BILLING_CONTACT_KEY])) {
            $this->getInfoInstance()->setAdditionalInformation(
                self::BILLING_CONTACT_KEY,
                $data['additional_data'][self::BILLING_CONTACT_KEY]
            );
        }

        return $this;
    }

    /**
     * Validate Apple Pay transaction data before order creation
     *
     * @param  string    $transactionData
     * @throws Exception
     */
    private function validateApplePayTransactionData($transactionData)
    {
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay $applePayConfig
         */
        $applePayConfig = $this->configProviderMethodFactory->get($this->_code);
        $integrationMode = $applePayConfig->getIntegrationMode();

        if ($integrationMode) {
            $this->logger2->addDebug('[Apple Pay SDK Mode] Validating client-side transaction data');

            if (empty($transactionData) || $transactionData === 'null' || trim($transactionData) === '') {
                $this->logger2->addError(sprintf(
                    '[Apple Pay SDK Mode] Invalid applepayTransaction data before order creation: %s',
                    var_export($transactionData, true)
                ));

                throw new Exception(
                    __('Apple Pay payment failed. The transaction data from your device is invalid. Please try again or use a different payment method.')
                );
            }

            $decodedJson = json_decode($transactionData, true);
            if (json_last_error() !== JSON_ERROR_NONE || empty($decodedJson['paymentData'])) {
                $this->logger2->addError(sprintf(
                    '[Apple Pay SDK Mode] Invalid JSON in applepayTransaction before order creation. Error: %s, Data: %s',
                    json_last_error_msg(),
                    substr($transactionData, 0, 100) . '...'
                ));

                throw new Exception(
                    __('Apple Pay payment failed. The transaction data format from your device is invalid. Please try again or use a different payment method.')
                );
            }

            $paymentData = $decodedJson['paymentData'];
            if (empty($paymentData['data']) || empty($paymentData['signature']) || empty($paymentData['header'])) {
                $this->logger2->addError('[Apple Pay SDK Mode] Missing required fields in paymentData before order creation');

                throw new Exception(
                    __('Apple Pay payment failed. The transaction data from your device is incomplete. Please try again or use a different payment method.')
                );
            }

            $this->logger2->addDebug(sprintf(
                '[Apple Pay SDK Mode] Valid applepayTransaction data validated before order creation. Length: %d characters',
                strlen($transactionData)
            ));
        } else {
            $this->logger2->addDebug('[Apple Pay Redirect Mode] Skipping transaction data validation - payment will be processed on Buckaroo hosted page');

            if (!empty($transactionData)) {
                $this->logger2->addDebug('[Apple Pay Redirect Mode] Unexpected transaction data present - this should be empty for redirect mode');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay $applePayConfig
         */
        $applePayConfig = $this->configProviderMethodFactory->get($this->_code);

        $integrationMode = $applePayConfig->getIntegrationMode();

        if ($integrationMode) {
            $applepayTransactionData = $payment->getAdditionalInformation(self::TRANSACTION_DATA_KEY);

            if (!empty($applepayTransactionData)) {
                // SDK mode with transaction data - use it
                $this->logger2->addDebug(sprintf(
                    '[Apple Pay SDK Mode] Using transaction data for order %s',
                    $payment->getOrder()->getIncrementId()
                ));

                $requestParameters = [
                    [
                        '_'    => $applepayTransactionData,
                        'Name' => 'PaymentData',
                    ],
                ];
            } else {
                $requestParameters = [];
            }

            $billingContact = $payment->getAdditionalInformation(self::BILLING_CONTACT_KEY) ?
                json_decode($payment->getAdditionalInformation(self::BILLING_CONTACT_KEY)) : null;
            if ($billingContact && !empty($billingContact->givenName) && !empty($billingContact->familyName)) {
                $requestParameters[] = [
                    '_'    => $billingContact->givenName . ' ' . $billingContact->familyName,
                    'Name' => 'CustomerCardName',
                ];
            }

            $services = [
                'Name'             => 'applepay',
                'Action'           => 'Pay',
                'Version'          => 0,
                'RequestParameter' => $requestParameters,
            ];

            $transactionBuilder->setOrder($payment->getOrder())
                ->setServices($services)
                ->setMethod('TransactionRequest');

        } else {
            $services = [
                'Name'             => 'applepay',
                'Action'           => 'Pay',
                'Version'          => 0,
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

    protected function getRefundTransactionBuilderVersion()
    {
        return null;
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
        return $this->buckarooPaymentMethodCode;
    }
}
