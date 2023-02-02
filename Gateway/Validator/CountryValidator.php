<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;

class CountryValidator extends AbstractValidator
{
    /**
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validate country
     *
     * @param array $validationSubject
     * @return bool
     * @throws NotFoundException
     * @throws \Exception
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $storeId = $validationSubject['storeId'];
        /** @var MethodInterface $methodInstance */
        $methodInstance = $validationSubject['methodInstance'] ?? null;

        if (
            $methodInstance instanceof MethodInterface
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
