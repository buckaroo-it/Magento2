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

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order\Address;

class EmailAddressDataBuilder extends AbstractDataBuilder
{
    /**
     * @var string
     */
    private $addressType;

    /**
     * @param string $addressType
     */
    public function __construct(string $addressType = 'billing')
    {
        $this->addressType = $addressType;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        /**
         * @var OrderAddressInterface $billingAddress
         */
        $address = $this->getAddress();

        return ['email' => $address->getEmail()];
    }

    /**
     * Get Address by address type
     *
     * @return OrderAddressInterface|Address|null
     */
    private function getAddress()
    {
        return ($this->addressType == 'shipping')
            ? $this->getOrder()->getShippingAddress() ?? $this->getOrder()->getBillingAddress()
            : $this->getOrder()->getBillingAddress();
    }
}
