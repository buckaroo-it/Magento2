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
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ApplepayDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $applepayTransaction = $paymentDO->getPayment()->getAdditionalInformation('applepayTransaction');

        return [
            'paymentData' => base64_encode(
                (string)$applepayTransaction
            ),
            'customerCardName' => $this->getCustomerCardName($paymentDO),
        ];
    }

    /**
     * Get the customer card name from Apple Pay transaction
     *
     * @param PaymentDataObjectInterface $paymentDO
     *
     * @return string Empty string if billing contact is not available
     */
    protected function getCustomerCardName(PaymentDataObjectInterface $paymentDO): string
    {
        $billingContact = \json_decode(
            stripslashes((string)$paymentDO->getPayment()->getAdditionalInformation('billingContact'))
        );

        if ($billingContact &&
            !empty($billingContact->givenName) &&
            !empty($billingContact->familyName)
        ) {
            return $billingContact->givenName . ' ' . $billingContact->familyName;
        }

        // Return empty string instead of null to satisfy SDK strict typing
        return '';
    }
}
