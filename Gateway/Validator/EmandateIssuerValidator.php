<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
/**
 * Class EmandateIssuerValidator
 */
class EmandateIssuerValidator extends AbstractValidator
{
    /**
     * @var ConfigInterface|\Buckaroo\Magento2\Model\ConfigProvider\Method\Emandate
     */
    private ConfigInterface $config;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigInterface $config
    ) {
        $this->config = $config;
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
      
        foreach ($this->config->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                return $this->createResult(true);
            }
        }

        return $this->createResult(false, [__('Please select a issuer from the list')]);
    }
}
