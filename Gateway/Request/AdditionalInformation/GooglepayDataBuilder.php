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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class GooglepayDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        // Get Google Pay payment data from additional information
        $googlepayPaymentData = $payment->getAdditionalInformation('googlepayPaymentData');

        if (!$googlepayPaymentData) {
            throw new \InvalidArgumentException('Google Pay payment data is missing');
        }

        // Decode the payment data
        $paymentData = json_decode($googlepayPaymentData, true);

        if (!$paymentData || !isset($paymentData['paymentMethodData']['tokenizationData']['token'])) {
            throw new \InvalidArgumentException('Google Pay token is missing from payment data');
        }

        // Extract the token data
        $token = $paymentData['paymentMethodData']['tokenizationData']['token'];

        // The token is already a JSON string, so we just need to base64 encode it
        $tokenString = is_string($token) ? $token : json_encode($token);

        // Prepare data for Buckaroo SDK (matching Apple Pay structure)
        $buckarooData = [
            'paymentData' => base64_encode($tokenString),
            'customerCardName' => $this->getCustomerCardName($paymentData),
        ];

        return $buckarooData;
    }

    /**
     * Get the customer card name from Google Pay transaction
     *
     * @param array $paymentData
     *
     * @return string Empty string if billing info is not available
     */
    protected function getCustomerCardName(array $paymentData): string
    {
        if (isset($paymentData['paymentMethodData']['info']['cardDetails'])) {
            return 'Google Pay User';
        }

        if (isset($paymentData['email'])) {
            return $paymentData['email'];
        }

        return '';
    }
}
