<?php

/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Model;

class ValidatorFactory
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $validators;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array                                     $validators
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
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
     * @throws \LogicException|\TIG\Buckaroo\Exception
     */
    public function get($validatorType)
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

        if (!isset($validatorClass) || empty($validatorClass)) {
            throw new \TIG\Buckaroo\Exception(
                new \Magento\Framework\Phrase(
                    'Unknown validator type requested: %1.',
                    [$validatorType]
                )
            );
        }

        $validator = $this->objectManager->get($validatorClass);
        if (!$validator instanceof ValidatorInterface) {
            throw new \LogicException(
                'The transaction builder must implement "TIG\Buckaroo\Model\ValidatorInterface".'
            );
        }
        return $validator;
    }
}
