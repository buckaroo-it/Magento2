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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Exception;
use Magento\Framework\ObjectManagerInterface;

class ValidatorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    protected array $validators;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param array                                     $validators
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
     * @return ValidatorInterface
     * @throws \LogicException|Exception
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
            throw new Exception(
                new \Magento\Framework\Phrase(
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
