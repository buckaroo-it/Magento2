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
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnCurrencyValidator extends AbstractValidator
{
    /**
     * Available Based on Currency
     *
     * @param  array           $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = false;

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $allowedCurrenciesRaw = $paymentMethodInstance->getConfigData('allowed_currencies');
        $allowedCurrencies = explode(',', (string)$allowedCurrenciesRaw);

        $currentCurrency = SubjectReader::readQuote($validationSubject)->getCurrency()->getQuoteCurrencyCode();

        if ($allowedCurrenciesRaw === null || in_array($currentCurrency, $allowedCurrencies)) {
            $isValid = true;
        }

        return $this->createResult($isValid);
    }
}
