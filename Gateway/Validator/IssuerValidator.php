<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Model\InfoInterface;

/**
 * Class IssuerValidator
 *
 * @package Magento\Payment\Gateway\Validator
 * @api
 * @since 100.0.2
 */
class IssuerValidator extends AbstractValidator
{
    /** @var ConfigProviderMethodFactory */
    private $configProviderFactory;

    /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface */
    private $config;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigProviderMethodFactory $configProviderFactory
     */
    public function __construct(
        ResultInterfaceFactory      $resultFactory,
        ConfigProviderMethodFactory $configProviderFactory
    ) {
        $this->configProviderFactory = $configProviderFactory;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {

        $paymentInfo = SubjectReader::readPayment($validationSubject)->getPayment();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this->createResult(true);
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        foreach ($this->getConfig($paymentInfo)->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                return $this->createResult(true);
            }
        }

        return $this->createResult(false, [__('Please select a issuer from the list')]);
    }

    /**
     * Get config provider class based on payment method name
     *
     * @param InfoInterface $paymentInfo
     * @return \Buckaroo\Magento2\Model\ConfigProvider\Method\ConfigProviderInterface|false
     * @throws \Buckaroo\Magento2\Exception
     */
    protected function getConfig($paymentInfo)
    {
        try {
            return $this->config = $this->configProviderFactory->get($paymentInfo->getMethodInstance()->getCode());
        } catch (\Exception $exception) {
            return false;
        }
    }
}
