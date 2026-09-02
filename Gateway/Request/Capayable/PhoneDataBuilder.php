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

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\Formatter\Address\PhoneFormatter;
use Magento\Sales\Api\Data\OrderAddressInterface;

class PhoneDataBuilder extends AbstractDataBuilder
{
    /**
     * @var PhoneFormatter
     */
    protected $phoneFormatter;

    /**
     * @param PhoneFormatter $phoneFormatter
     */
    public function __construct(PhoneFormatter $phoneFormatter)
    {
        $this->phoneFormatter = $phoneFormatter;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);

        return [
            'phone' => [
                'mobile' => $this->getCleanPhone(
                    $this->getOrder()->getBillingAddress()
                )
            ]
        ];
    }

    /**
     * Format phone number
     *
     * @param OrderAddressInterface $billingAddress
     *
     * @return mixed
     */
    protected function getCleanPhone(OrderAddressInterface $billingAddress)
    {
        $phoneData = $this->phoneFormatter->format(
            $billingAddress->getTelephone(),
            $billingAddress->getCountryId()
        );
        return $phoneData['clean'];
    }
}
