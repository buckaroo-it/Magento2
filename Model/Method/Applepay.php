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
            $applepayEncoded = base64_encode($data['additional_data']['applepayTransaction']);

            $this->getInfoInstance()->setAdditionalInformation('applepayTransaction', $applepayEncoded);
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
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

//        $requestParameters = [
//            [
//                '_'    => $payment->getAdditionalInformation('applepayTransaction'),
//                'Name' => 'PaymentData',
//            ]
//        ];

//        $billingContact = $payment->getAdditionalInformation('billingContact') ?
//            json_decode($payment->getAdditionalInformation('billingContact')) : null;
//        if ($billingContact && !empty($billingContact->givenName) && !empty($billingContact->familyName)) {
//            $requestParameters[] = [
//                '_'    => $billingContact->givenName . ' ' . $billingContact->familyName,
//                'Name' => 'CustomerCardName',
//            ];
//        }
//
//        $services = [
//            'Name'             => 'applepay',
//            'Action'           => 'Pay',
//            'Version'          => 0,
//            'RequestParameter' => $requestParameters,
//        ];

//        /**
//         * @noinspection PhpUndefinedMethodInspection
//         */
//        $transactionBuilder->setOrder($payment->getOrder())
//            ->setServices($services)
//            ->setMethod('TransactionRequest');

        $customVars = [
            'ContinueOnIncomplete' => '1',
        ];


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
            ->setCustomVars($customVars)
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
