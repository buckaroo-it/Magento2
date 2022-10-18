<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Gateway\Validator\ResultInterface;

/**
 * Class IssuerValidator
 * @package Magento\Payment\Gateway\Validator
 * @api
 * @since 100.0.2
 */
class IssuerValidator extends AbstractValidator
{
    /** @var Factory */
    private $configProvider;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Factory $configProvider
    ) {
        $this->configProvider = $configProvider;
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return bool|ResultInterface
     * @throws NotFoundException
     * @throws \Exception
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentInfo = $validationSubject['payment'];

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

    protected function getConfig($paymentInfo)
    {
        return $this->config = $this->configProvider->get(
            $paymentInfo->getMethod()
        );
    }
}
