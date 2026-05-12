<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
    private State $state;
    private MethodConfigProviderFactory $methodConfigProviderFactory;

    public function __construct(
        ResultInterfaceFactory $resultFactory,
        State $state,
        MethodConfigProviderFactory $methodConfigProviderFactory
    ) {
        $this->state = $state;
        $this->methodConfigProviderFactory = $methodConfigProviderFactory;
        parent::__construct($resultFactory);
    }

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
