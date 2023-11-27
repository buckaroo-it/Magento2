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
use Magento\Framework\Exception\LocalizedException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\IdealProcessing as IdealProcessingConfig;

class IdealProcessing extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_idealprocessing';

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
            'Name'             => 'idealprocessing',
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
            'Version'          => 2,
            'RequestParameter' => $this->getOrderRequestParameters($payment)
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    private function getOrderRequestParameters($payment): array
    {
        $parameters = [];

        if ($this->canShowIssuers()) {
            $parameters = [[
                '_'    => $payment->getAdditionalInformation('issuer'),
                'Name' => 'issuer',
            ]];
        }
        return $parameters;
    }
    /**
     * Can show issuers in the checkout form
     *
     * @return boolean
     */
    private function canShowIssuers() {
        return $this->getConfigData('show_issuers') == 1;
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
     * Validate that we received a valid issuer ID.
     *
     * {@inheritdoc}
     */
    public function validate()
    {
        parent::validate();

        /** @var IdealProcessingConfig $config */
        $config = $this->objectManager->get(IdealProcessingConfig::class);

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

        if (!$valid && $this->canShowIssuers()) {
            throw new LocalizedException(__('Please select a issuer from the list'));
        }

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
}
