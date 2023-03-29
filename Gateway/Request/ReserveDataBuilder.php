<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Resources\Constants\Gender;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class ReserveDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $payment = $paymentDO->getPayment();
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $order->getBillingAddress();
        $country = $billingAddress->getCountryId();
        $birthDayStamp = str_replace('/', '', (string)$payment->getAdditionalInformation('customer_DoB'));
        $gender = Gender::FEMALE;
        if ($payment->getAdditionalInformation('customer_gender') === '1') {
            $gender = Gender::MALE;
        }
        return [
            'operatingCountry' => $country,
            'pno' => empty($birthDayStamp) ? '01011990' : $birthDayStamp,
            'gender' => $gender
        ];
    }
}
