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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

class ValidatorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $validators;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array                  $validators
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        array $validators = []
    ) {
        $this->objectManager = $objectManager;
        $this->validators = $validators;
    }

    /**
     * Retrieve proper transaction builder for the specified transaction type.
     *
     * @param string $validatorType
     *
     * @throws \LogicException|Exception
     *
     * @return ValidatorInterface
     */
    public function get(string $validatorType)
    {
        if (empty($this->validators)) {
            throw new \LogicException('Validator adapter is not set.');
        }
        foreach ($this->validators as $validatorMetaData) {
            $validatorMetaDataType = $validatorMetaData['type'];
            if ($validatorMetaDataType == $validatorType) {
                $validatorClass = $validatorMetaData['model'];
                break;
            }
        }

        if (empty($validatorClass)) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new Exception(
                new Phrase(
                    'Unknown validator type requested: %1.',
                    [$validatorType]
                )
            );
        }

        $validator = $this->objectManager->get($validatorClass);
        if (!$validator instanceof ValidatorInterface) {
            throw new \LogicException(
                'The transaction builder must implement "Buckaroo\Magento2\Model\ValidatorInterface".'
            );
        }
        return $validator;
    }
}
