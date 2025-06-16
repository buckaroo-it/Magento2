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
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ApplepayRedirectDataBuilder implements BuilderInterface
{
    /**
     * @var Applepay
     */
    private Applepay $configProvider;

    /**
     * @param Applepay $configProvider
     */
    public function __construct(
        Applepay $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        
        // Check if this is redirect mode
        $integrationMode = $this->configProvider->getIntegrationMode();
        
        if ($integrationMode === '1') {
            // Redirect mode - add Apple Pay service for Buckaroo Hosted Payment Page
            return [
                'servicesSelectableByClient' => 'applepay',
                'continueOnIncomplete' => '1',
            ];
        }

        // Inline mode - use existing data builder logic
        return [
            'paymentData' => base64_encode(
                (string)$payment->getAdditionalInformation('applepayTransaction')
            ),
            'customerCardName' => $this->getCustomerCardName($paymentDO),
        ];
    }

    /**
     * Get customer card name from Apple Pay transaction
     *
     * @param \Magento\Payment\Gateway\Data\PaymentDataObjectInterface $paymentDO
     * @return string|null
     */
    protected function getCustomerCardName($paymentDO): ?string
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

        return null;
    }
} 