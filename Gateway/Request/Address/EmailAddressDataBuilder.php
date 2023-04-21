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

namespace Buckaroo\Magento2\Gateway\Request\Address;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order\Address;

class EmailAddressDataBuilder extends AbstractDataBuilder
{
    /**
     * @var string
     */
    private string $addressType;

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
