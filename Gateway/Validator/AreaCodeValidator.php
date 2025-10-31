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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AreaCodeValidator extends AbstractValidator
{
    /**
     * @var State
     */
    private $state;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param State                  $state
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
     * @param  array              $validationSubject
     * @throws Exception
     * @throws LocalizedException
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $isAvailableInBackend = $paymentMethodInstance->getConfigData('available_in_backend');
        $areaCode = $this->state->getAreaCode();
        if (Area::AREA_ADMINHTML === $areaCode
            && $isAvailableInBackend !== null
            && $isAvailableInBackend == 0
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
