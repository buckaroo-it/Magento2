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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AllowedGiftcardsValidator extends AbstractValidator
{
    /**
     * @var GiftcardsConfig
     */
    private $giftcardsConfig;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param GiftcardsConfig        $giftcardsConfig
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        GiftcardsConfig $giftcardsConfig
    ) {
        $this->giftcardsConfig = $giftcardsConfig;
        parent::__construct($resultFactory);
    }

    /**
     * Validates the payment information for Buckaroo gateway.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        /**
         * If there are no giftcards chosen, we can't be available
         */
        $fails = [];
        if (null === $this->giftcardsConfig->getAllowedGiftcards()) {
            $fails[] = __('There are no allowed giftcards.');
            $isValid = false;
        }

        return $this->createResult($isValid, $fails);
    }
}
