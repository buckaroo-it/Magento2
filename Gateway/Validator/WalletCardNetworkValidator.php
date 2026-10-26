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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Buckaroo\Magento2\Observer\DataAssignObserver;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Model\InfoInterface;

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
        $network = $payment instanceof InfoInterface ? $this->getCardNetwork($payment) : '';

        if (in_array(strtolower($network), self::UNSUPPORTED_NETWORKS, true)) {
            return $this->createResult(
                false,
                [__('This card is not supported for this payment method. Please choose another card.')]
            );
        }

        return $this->createResult(true);
    }

    /**
     * Read the card network from the wallet data of the payment's own method
     *
     * The quote payment can still hold data of another wallet the shopper tried first, so only the
     * data of the method being validated is read.
     *
     * @param InfoInterface $payment
     *
     * @return string Empty string when no network is known, e.g. in redirect mode
     */
    private function getCardNetwork(InfoInterface $payment): string
    {
        switch ($payment->getMethodInstance()->getCode()) {
            case Applepay::CODE:
                $network = $payment->getAdditionalInformation(DataAssignObserver::APPLEPAY_CARD_NETWORK);
                break;
            case Googlepay::CODE:
                $network = $this->getGooglepayCardNetwork(
                    $payment->getAdditionalInformation(DataAssignObserver::GOOGLEPAYPAYMENTDATA)
                );
                break;
            default:
                $network = '';
        }

        return is_string($network) ? $network : '';
    }

    /**
     * Read the card network from the Google Pay payment data JSON
     *
     * @param mixed $paymentData
     *
     * @return mixed
     */
    private function getGooglepayCardNetwork($paymentData)
    {
        if (!is_string($paymentData) || $paymentData === '') {
            return '';
        }

        $paymentData = json_decode($paymentData, true);

        return $paymentData['paymentMethodData']['info']['cardNetwork'] ?? '';
    }
}
