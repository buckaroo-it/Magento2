<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BillingAddress;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class NameDataBuilder implements BuilderInterface
{
    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $order->getBillingAddress();

        return ['customer' => ['name' => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastName()]];
    }
}
