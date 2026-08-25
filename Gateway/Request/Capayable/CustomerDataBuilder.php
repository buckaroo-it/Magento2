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

namespace Buckaroo\Magento2\Gateway\Request\Capayable;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;
use Magento\Sales\Api\Data\OrderAddressInterface;

class CustomerDataBuilder extends AbstractDataBuilder
{
    /**
     * @var CultureCodeResolver
     */
    private $cultureCodeResolver;

    /**
     * @param CultureCodeResolver $cultureCodeResolver
     */
    public function __construct(CultureCodeResolver $cultureCodeResolver)
    {
        $this->cultureCodeResolver = $cultureCodeResolver;
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
        $billingAddress = $this->getOrder()->getBillingAddress();
        return [
            'customer' => [
                'initials'  => $this->getInitials($billingAddress->getFirstname()),
                'lastName'  => $billingAddress->getLastname(),
                'email'     => $billingAddress->getEmail(),
                'phone'     => $billingAddress->getTelephone(),
                'culture'   => $this->cultureCodeResolver->resolveDebtorCultureForOrder($this->getOrder()),
                'birthDate' => $this->getCustomerBirthDate()
            ]
        ];
    }

    /**
     * Get initial from the first name
     *
     * @param string $name
     *
     * @return string
     */
    protected function getInitials(string $name): string
    {
        $initials = '';
        $nameParts = explode(' ', $name);

        foreach ($nameParts as $part) {
            $initials .= strtoupper(substr($part, 0, 1)) . '.';
        }

        return $initials;
    }

    /**
     * Get a customer birthdate
     *
     * @return string
     */
    protected function getCustomerBirthDate()
    {
        return str_replace('/', '-', (string)$this->getPayment()->getAdditionalInformation('customer_DoB'));
    }
}
