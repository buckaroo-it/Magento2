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

class Mrcash extends AbstractMethod
{
    /**
     * Payment Code
     */
    public const PAYMENT_METHOD_CODE = 'buckaroo_magento2_mrcash';

    public const REFUND_EXTRA_FIELDS_XPATH = 'payment/buckaroo_magento2_mrcash/refund_extra_fields';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'mrcash';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $storeId = $payment->getOrder()->getStoreId();
        $useClientSide = $this->getConfigData('client_side', $storeId);
        $this->logger2->addDebug(__METHOD__.'|1|'.var_export($useClientSide, true));

        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        if ($useClientSide &&
            ($additionalInformation = $payment->getAdditionalInformation()) &&
            isset($additionalInformation['client_side_mode']) &&
            ($additionalInformation['client_side_mode'] == 'cc')
        ) {
            $this->logger2->addDebug(__METHOD__ . '|5|');

            if (!isset($additionalInformation['customer_encrypteddata'])) {
                throw new \Buckaroo\Magento2\Exception(
                    __('An error occured trying to send the encrypted bancontact data to Buckaroo.')
                );
            }

            $services = [
                'Name' => 'bancontactmrcash',
                'Action' => 'PayEncrypted',
                'Version' => 0,
                'RequestParameter' => [
                    [
                        '_' => $additionalInformation['customer_encrypteddata'],
                        'Name' => 'EncryptedCardData',
                    ],
                ],
            ];
        } else {
            $services = [
                'Name'             => 'bancontactmrcash',
                'Action'           => 'Pay',
                'Version'          => 1,
            ];
        }

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
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);

        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['customer_encrypteddata'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_encrypteddata',
                $data['additional_data']['customer_encrypteddata']
            );
        }

        if (isset($data['additional_data']['client_side_mode'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'client_side_mode',
                $data['additional_data']['client_side_mode']
            );
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @throws \Buckaroo\Magento2\Exception
     * @return array
     */
    public function getEncyptedPaymentsService($payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();

        if (!isset($additionalInformation['customer_encrypteddata'])) {
            throw new \Buckaroo\Magento2\Exception(
                __('An error occured trying to send the encrypted bancontact data to Buckaroo.')
            );
        }

        $services = [
            'Name'             => 'bancontactmrcash',
            'Action'           => 'PayEncrypted',
            'Version'          => 0,
            'RequestParameter' => [
                [
                    '_'    => $additionalInformation['customer_encrypteddata'],
                    'Name' => 'EncryptedCardData',
                ],
            ],
        ];

        return $services;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return 'bancontactmrcash';
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
