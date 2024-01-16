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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Billink;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class AvailableBasedOnAmountValidator extends AbstractValidator
{
    /**
     * Check if the grand total exceeds the maximum allowed total.
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);

        $quote = SubjectReader::readQuote($validationSubject);

        $storeId = $quote->getStoreId();

        if (($quote->getShippingAddress()->getCompany() || $quote->getBillingAddress()->getCompany())
            && in_array($paymentMethodInstance->getCode(), [Afterpay20::CODE, Billink::CODE])) {
            $maximum = $paymentMethodInstance->getConfigData('max_amount_b2b', $storeId);
            $minimum = $paymentMethodInstance->getConfigData('min_amount_b2b', $storeId);
        } else {
            $maximum = $paymentMethodInstance->getConfigData('max_amount', $storeId);
            $minimum = $paymentMethodInstance->getConfigData('min_amount', $storeId);
        }

        $total = $quote->getGrandTotal();

        if ($total < 0.01
            || $maximum !== null && $total > $maximum
            || $minimum !== null && $total < $minimum
        ) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
