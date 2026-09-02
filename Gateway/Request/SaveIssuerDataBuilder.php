<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\CustomerAttributes;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

class SaveIssuerDataBuilder implements BuilderInterface
{
    public const EAV_LAST_USED_ISSUER_ID = 'buckaroo_last_paybybank_issuer';

    /**
     * @var CustomerAttributes
     */
    protected $customerAttributes;

    /**
     * @param CustomerAttributes $customerAttributes
     */
    public function __construct(
        CustomerAttributes $customerAttributes
    ) {
        $this->customerAttributes = $customerAttributes;
    }

    /**
     * Save last used issuer, it will be used to select automatically the issuer in the checkout
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $this->saveLastUsedIssuer(SubjectReader::readPayment($buildSubject)->getPayment());
        return [];
    }

    /**
     * Save the last used issuer as a customer attribute.
     *
     * @param InfoInterface|OrderPaymentInterface $payment
     */
    public function saveLastUsedIssuer($payment): void
    {
        /** @var Order $order */
        $order = $payment->getOrder();
        $customerId = $order->getCustomerId();

        if ($customerId !== null) {
            $this->customerAttributes->setAttribute(
                (int)$customerId,
                self::EAV_LAST_USED_ISSUER_ID,
                $payment->getAdditionalInformation('issuer')
            );
        }
    }
}
