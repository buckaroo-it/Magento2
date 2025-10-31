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
     * @param  array           $validationSubject
     * @return ResultInterface
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
