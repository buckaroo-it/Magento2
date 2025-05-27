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
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_applepay';

    /** @var string */
    public $buckarooPaymentMethodCode = 'applepay';

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
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['applepayTransaction'])) {
            $transactionData = $data['additional_data']['applepayTransaction'];

            $this->validateApplePayTransactionData($transactionData);

            $applepayEncoded = base64_encode($transactionData);
            $this->getInfoInstance()->setAdditionalInformation('applepayTransaction', $applepayEncoded);

            $this->logger2->addDebug(sprintf(
                '[Apple Pay] Transaction data assigned for payment. Length: %d characters',
                strlen($transactionData)
            ));
        } else {
            $this->logger2->addError('[Apple Pay] Missing applepayTransaction data in payment assignment - preventing order creation');

            throw new Exception(
                __('Apple Pay transaction data is missing. Please try the payment again.')
            );
        }

        if (!empty($data['additional_data']['billingContact'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'billingContact',
                $data['additional_data']['billingContact']
            );
        }

        return $this;
    }

    /**
     * Validate Apple Pay transaction data before order creation
     *
     * @param string $transactionData
     * @throws Exception
     */
    private function validateApplePayTransactionData($transactionData)
    {
        if (empty($transactionData) || $transactionData === 'null' || trim($transactionData) === '') {
            $this->logger2->addError(sprintf(
                '[Apple Pay] Invalid applepayTransaction data before order creation: %s',
                var_export($transactionData, true)
            ));

            throw new Exception(
                __('Apple Pay transaction data is invalid. Please try the payment again.')
            );
        }

        $decodedJson = json_decode($transactionData, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decodedJson['paymentData'])) {
            $this->logger2->addError(sprintf(
                '[Apple Pay] Invalid JSON in applepayTransaction before order creation. Error: %s, Data: %s',
                json_last_error_msg(),
                substr($transactionData, 0, 100) . '...'
            ));

            throw new Exception(
                __('Apple Pay transaction data format is invalid. Please try the payment again.')
            );
        }

        $paymentData = $decodedJson['paymentData'];
        if (empty($paymentData['data']) || empty($paymentData['signature']) || empty($paymentData['header'])) {
            $this->logger2->addError('[Apple Pay] Missing required fields in paymentData before order creation');

            throw new Exception(
                __('Apple Pay transaction data is incomplete. Please try the payment again.')
            );
        }

        $this->logger2->addDebug(sprintf(
            '[Apple Pay] Valid applepayTransaction data validated before order creation. Length: %d characters',
            strlen($transactionData)
        ));
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
            // Client Side SDK logic
            $applepayTransactionData = $payment->getAdditionalInformation('applepayTransaction');

            if (empty($applepayTransactionData)) {
                throw new Exception(
                    __('Apple Pay transaction data is missing. Please try again.')
                );
            }

            $requestParameters = [
                [
                    '_'    => $applepayTransactionData,
                    'Name' => 'PaymentData',
                ]
            ];

            $billingContact = $payment->getAdditionalInformation('billingContact') ?
                json_decode($payment->getAdditionalInformation('billingContact')) : null;
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
            // RedirectToHTML logic
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
