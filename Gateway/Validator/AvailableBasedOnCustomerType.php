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
     * @param  array           $validationSubject
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
