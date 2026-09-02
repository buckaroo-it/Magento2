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
use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;
use Buckaroo\Magento2\Model\Config\Source\BillinkCustomerType;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Billink;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class AvailableBasedOnCustomerType extends AbstractValidator
{
    /**
     * Check if the payment method should be shown according to the configured customer type.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $quote = SubjectReader::readQuote($validationSubject);

        $storeId = $quote->getStoreId();

        if (($quote->getShippingAddress()->getCompany() || $quote->getBillingAddress()->getCompany())
            && (
                $paymentMethodInstance->getConfigData('customer_type', $storeId) == AfterpayCustomerType::CUSTOMER_TYPE_B2C
                || $paymentMethodInstance->getConfigData('customer_type', $storeId) == BillinkCustomerType::CUSTOMER_TYPE_B2C
            )) {
            $isValid = false;
        }
        return $this->createResult($isValid);
    }
}
