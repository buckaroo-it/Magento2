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
use Buckaroo\Magento2\Model\ConfigProvider\Method\ZakelijkOpRekening;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class AvailableBasedOnCompanyValidator extends AbstractValidator
{
    /**
     * B2B-only methods require a company name on the billing or shipping address.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        if ($paymentMethodInstance->getCode() !== ZakelijkOpRekening::CODE) {
            return $this->createResult(true);
        }

        $quote = SubjectReader::readQuote($validationSubject);
        $billingCompany = trim((string)$quote->getBillingAddress()->getCompany());
        $shippingCompany = trim((string)$quote->getShippingAddress()->getCompany());

        return $this->createResult($billingCompany !== '' || $shippingCompany !== '');
    }
}
