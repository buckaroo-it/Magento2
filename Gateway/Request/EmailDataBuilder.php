<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

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
