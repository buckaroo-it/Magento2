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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard as CreditcardConfig;
use Buckaroo\Magento2\Observer\DataAssignObserver;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Accept a chosen card brand only when the merchant allows it.
 *
 * Registered as the "global" validator, so it runs when the payment is assigned and placed, and
 * not on capture or refund of an order whose brand was allowed at the time.
 */
class AllowedCreditcardTypeValidator extends AbstractValidator
{
    /**
     * @var CreditcardConfig
     */
    private $creditcardConfig;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param CreditcardConfig       $creditcardConfig
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        CreditcardConfig $creditcardConfig
    ) {
        $this->creditcardConfig = $creditcardConfig;
        parent::__construct($resultFactory);
    }

    /**
     * Validate the card brand chosen for the payment
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = $validationSubject['payment'] ?? null;
        $cardType = $payment ? $payment->getAdditionalInformation(DataAssignObserver::CARD_TYPE) : null;

        // Without a chosen brand the shopper picks one on the Buckaroo payment page
        if ($cardType === null || $cardType === '') {
            return $this->createResult(true);
        }

        $allowed = array_map(
            'strtolower',
            array_map('trim', explode(',', (string)$this->creditcardConfig->getAllowedCreditcards(
                $validationSubject['storeId'] ?? null
            )))
        );

        if (is_string($cardType) && in_array(strtolower($cardType), $allowed, true)) {
            return $this->createResult(true);
        }

        return $this->createResult(false, [__('The selected card type is not available. Please choose another card.')]);
    }
}
