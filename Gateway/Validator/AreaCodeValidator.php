<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class AreaCodeValidator extends AbstractValidator
{
    private ConfigProviderFactory $configProviderFactory;
    private State $state;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigProviderFactory $configProviderFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigProviderFactory $configProviderFactory,
        State $state
    ) {
        $this->configProviderFactory = $configProviderFactory;
        $this->state = $state;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject)
    {
        $isValid = true;

        if (!isset($validationSubject['payment'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        $paymentMethodInstance = $validationSubject['payment'];
        $areaCode = $this->state->getAreaCode();
        /**
         * @var AbstractConfigProvider
         */
        $config = $this->configProviderFactory->get($paymentMethodInstance->buckarooPaymentMethodCode);
        if (
            Area::AREA_ADMINHTML === $areaCode
            && $config->getValue('available_in_backend') !== null
            && $config->getValue('available_in_backend') == 0
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
