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
