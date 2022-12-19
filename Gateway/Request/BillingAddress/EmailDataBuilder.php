<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\BillingAddress;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class EmailDataBuilder implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $billingAddress = $paymentDO->getOrder()->getBillingAddress();
        return ['email' => $billingAddress->getEmail()];
    }
}
