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

use Buckaroo\Magento2\Observer\DataAssignObserver;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

/**
 * Reject an Apple Pay or Google Pay payment made with a card network Buckaroo does not process.
 *
 * The payment sheets already hide these networks; this stops a token that gets through anyway before
 * the transaction request is sent. Registered as the "global" validator, so it runs when the payment
 * is assigned and placed, and not on capture or refund.
 */
class WalletCardNetworkValidator extends AbstractValidator
{
    private const UNSUPPORTED_NETWORKS = ['amex', 'discover', 'jcb'];

    /**
     * Validate the card network of the Apple Pay or Google Pay payment
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $payment = $validationSubject['payment'] ?? null;
        $network = $payment ? $this->getCardNetwork($payment) : '';

        if (in_array(strtolower($network), self::UNSUPPORTED_NETWORKS, true)) {
            return $this->createResult(
                false,
                [__('This card is not supported for this payment method. Please choose another card.')]
            );
        }

        return $this->createResult(true);
    }

    /**
     * Read the card network from the Apple Pay or Google Pay payment data
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     *
     * @return string Empty string when no network is known, e.g. in redirect mode
     */
    private function getCardNetwork($payment): string
    {
        $applepayNetwork = $payment->getAdditionalInformation(DataAssignObserver::APPLEPAY_CARD_NETWORK);
        if (is_string($applepayNetwork) && $applepayNetwork !== '') {
            return $applepayNetwork;
        }

        $googlepayPaymentData = $payment->getAdditionalInformation(DataAssignObserver::GOOGLEPAYPAYMENTDATA);
        if (!is_string($googlepayPaymentData) || $googlepayPaymentData === '') {
            return '';
        }

        $paymentData = json_decode($googlepayPaymentData, true);
        $network = $paymentData['paymentMethodData']['info']['cardNetwork'] ?? '';

        return is_string($network) ? $network : '';
    }
}
