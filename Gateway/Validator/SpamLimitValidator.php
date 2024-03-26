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
use Buckaroo\Magento2\Service\SpamLimitService;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class SpamLimitValidator extends AbstractValidator
{
    /**
     * @var SpamLimitService
     */
    private SpamLimitService $spamLimitService;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param SpamLimitService $spamLimitService
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
