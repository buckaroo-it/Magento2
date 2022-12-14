<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class NameDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
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
