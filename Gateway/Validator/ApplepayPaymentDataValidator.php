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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * In inline mode an Apple Pay order needs the payment data returned by the Apple Pay sheet.
 *
 * Runs as part of the "global" validator. It only checks the order payment, so selecting Apple Pay
 * on the quote keeps working before the shopper has paid. Redirect mode is not affected: the shopper
 * completes Apple Pay at Buckaroo.
 */
class ApplepayPaymentDataValidator extends AbstractValidator
{
    private const INLINE_MODE = '0';

    /**
     * @var Applepay
     */
    private $applepayConfig;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Applepay               $applepayConfig
     */
    public function __construct(ResultInterfaceFactory $resultFactory, Applepay $applepayConfig)
    {
        parent::__construct($resultFactory);
        $this->applepayConfig = $applepayConfig;
    }

    /**
     * Refuse an inline Apple Pay order without Apple Pay payment data
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = $validationSubject['payment'] ?? null;

        if (!$payment instanceof OrderPaymentInterface
            || $payment->getMethod() !== Applepay::CODE
            || (string)$this->applepayConfig->getIntegrationMode($this->getStoreId($payment)) !== self::INLINE_MODE
        ) {
            return $this->createResult(true);
        }

        $paymentData = $payment->getAdditionalInformation('applepayTransaction');
        if (is_string($paymentData) && trim($paymentData) !== '') {
            return $this->createResult(true);
        }

        return $this->createResult(
            false,
            [__('Apple Pay could not be completed. Please try again.')]
        );
    }

    /**
     * Store of the order being placed
     *
     * @param OrderPaymentInterface $payment
     *
     * @return int|null
     */
    private function getStoreId(OrderPaymentInterface $payment): ?int
    {
        if (!$payment instanceof OrderPayment || $payment->getOrder() === null) {
            return null;
        }

        return StoreId::normalize($payment->getOrder()->getStoreId());
    }
}
