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

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;

class CountryValidator extends AbstractValidator
{
    /**
     * Validate country
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;
        $storeId = $validationSubject['storeId'];
        /** @var MethodInterface $methodInstance */
        $methodInstance = $validationSubject['methodInstance'] ?? null;

        if ($methodInstance instanceof MethodInterface
            && (int)$methodInstance->getConfigData('allowspecific', $storeId) === 1
        ) {
            $availableCountries = explode(
                ',',
                $methodInstance->getConfigData('specificcountry', $storeId) ?? ''
            );

            if (!in_array($validationSubject['country'], $availableCountries)) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}
