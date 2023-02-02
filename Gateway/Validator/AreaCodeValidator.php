<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Payment\Model\MethodInterface;

class AreaCodeValidator extends AbstractValidator
{
    /**
     * @var State
     */
    private State $state;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param State $state
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        State $state
    ) {
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

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $areaCode = $this->state->getAreaCode();
        if (
            Area::AREA_ADMINHTML === $areaCode
            && $paymentMethodInstance->getConfigData('available_in_backend') !== null
            && $paymentMethodInstance->getConfigData('available_in_backend') == 0
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
