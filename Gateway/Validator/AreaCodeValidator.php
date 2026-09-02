<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AreaCodeValidator extends AbstractValidator
{
    /**
     * @var State
     */
    private State $state;

    /**
     * @var MethodConfigProviderFactory
     */
    private MethodConfigProviderFactory $methodConfigProviderFactory;

    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param State $state
     * @param MethodConfigProviderFactory $methodConfigProviderFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        State $state,
        MethodConfigProviderFactory $methodConfigProviderFactory
    ) {
        $this->state = $state;
        $this->methodConfigProviderFactory = $methodConfigProviderFactory;
        parent::__construct($resultFactory);
    }

    /**
     * Validate that the payment method is allowed for the current area code.
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;
        $method = SubjectReader::readPaymentMethodInstance($validationSubject);

        try {
            $areaCode = $this->state->getAreaCode();
        } catch (\Exception $e) {
            return $this->createResult(true);
        }

        // Block methods that are disabled in backend
        $isAvailableInBackend = $method->getConfigData('available_in_backend');
        if ($areaCode === Area::AREA_ADMINHTML && $isAvailableInBackend !== null && (int)$isAvailableInBackend === 0) {
            $isValid = false;
        }

        // Check area-code visibility for any method that declares it (e.g. PayPerEmail, PayLink)
        if ($isValid && $this->methodConfigProviderFactory->has($method->getCode())) {
            $cp = $this->methodConfigProviderFactory->get($method->getCode());
            if (method_exists($cp, 'isVisibleForAreaCode') && !$cp->isVisibleForAreaCode($areaCode)) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}
