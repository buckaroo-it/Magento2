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

use Buckaroo\Magento2\Helper\StoreId;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment as OrderPayment;

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
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        /**
         * If there are no giftcards chosen, we can't be available
         */
        $fails = [];

        if (null === $this->giftcardsConfig->getAllowedGiftcards($this->getStoreId($validationSubject))) {
            $fails[] = __('There are no allowed giftcards.');
            $isValid = false;
        }

        return $this->createResult($isValid, $fails);
    }

    /**
     * Store of the quote or order being validated; null means the ambient store
     *
     * Magento's availability check passes only the payment data object, no quote.
     *
     * @param array $validationSubject
     *
     * @return int|null
     */
    private function getStoreId(array $validationSubject): ?int
    {
        if (isset($validationSubject['quote']) && $validationSubject['quote'] instanceof CartInterface) {
            return StoreId::normalize($validationSubject['quote']->getStoreId());
        }

        $paymentDO = $validationSubject['payment'] ?? null;
        if (!$paymentDO instanceof PaymentDataObjectInterface) {
            return null;
        }

        $payment = $paymentDO->getPayment();
        if ($payment instanceof QuotePayment && $payment->getQuote()) {
            return StoreId::normalize($payment->getQuote()->getStoreId());
        }

        if ($payment instanceof OrderPayment && $payment->getOrder()) {
            return StoreId::normalize($payment->getOrder()->getStoreId());
        }

        return null;
    }
}
