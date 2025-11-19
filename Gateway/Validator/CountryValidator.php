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
