<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\Method\Adapter as PaymentMethodAdapter;

class AreaCodeValidator extends AbstractValidator
{
    /**
     * @var ConfigProviderMethodFactory
     */
    private ConfigProviderMethodFactory $configProviderFactory;

    /**
     * @var State
     */
    private State $state;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigProviderMethodFactory $configProviderFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigProviderMethodFactory  $configProviderFactory,
        State $state
    ) {
        $this->configProviderFactory = $configProviderFactory;
        $this->state = $state;
        parent::__construct($resultFactory);
    }

    /**
     * Validate Area Code Value
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;

        if (!isset($validationSubject['paymentMethodInstance'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        /** @var MethodInterface $paymentMethodInstance */
        $paymentMethodInstance = $validationSubject['paymentMethodInstance'];

        $areaCode = $this->state->getAreaCode();
        if (Area::AREA_ADMINHTML === $areaCode
            && $paymentMethodInstance->getConfigData('available_in_backend') !== null
            && $paymentMethodInstance->getConfigData('available_in_backend') == 0
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
