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

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;

class PersonDataBuilder extends AbstractDataBuilder
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

        $address = $this->getOrder()->getBillingAddress();
        if ($address === null) {
            return [];
        }

        return [
            'culture'   => $this->cultureCodeResolver->resolveDebtorCultureForOrder($this->getOrder()),
            'name'      => $address->getFirstname() . ' ' . $address->getLastname(),
            'firstName' => $address->getFirstname(),
            'lastName'  => $address->getLastname()
        ];
    }
}
