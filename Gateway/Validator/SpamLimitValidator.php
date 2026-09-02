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
use Buckaroo\Magento2\Service\SpamLimitService;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class SpamLimitValidator extends AbstractValidator
{
    /**
     * @var SpamLimitService
     */
    private $spamLimitService;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SpamLimitService       $spamLimitService
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SpamLimitService $spamLimitService
    ) {
        parent::__construct($resultFactory);
        $this->spamLimitService = $spamLimitService;
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $isValid = true;
        if ($this->spamLimitService->isSpamLimitActive($paymentMethodInstance)
            && $this->spamLimitService->isSpamLimitReached(
                $paymentMethodInstance,
                $this->spamLimitService->getPaymentAttemptsStorage()
            )) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
