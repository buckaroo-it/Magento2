<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Service\CreditManagement\ServiceParameters;
use Buckaroo\Magento2\Service\PayReminderService;

class CreditcardEncryptedDataBuilder extends AbstractDataBuilder
{
    /** @var ServiceParameters */
    private ServiceParameters $serviceParameters;
    private PayReminderService $payReminderService;

    public function __construct(
        ServiceParameters  $serviceParameters,
        PayReminderService $payReminderService)
    {
        $this->serviceParameters = $serviceParameters;
        $this->payReminderService = $payReminderService;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        $additionalInformation = $this->getPayment()->getAdditionalInformation();

        if (!isset($additionalInformation['customer_encrypteddata'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the encrypted creditcard data to Buckaroo.'));
        }

        if (!isset($additionalInformation['customer_creditcardcompany'])) {
            throw new \Buckaroo\Magento2\Exception(__('An error occured trying to send the creditcard company data to Buckaroo.'));
        }

        return [
            'name' => $additionalInformation['customer_creditcardcompany'],
            'encryptedCardData' => $additionalInformation['customer_encrypteddata']
        ];
    }
}
