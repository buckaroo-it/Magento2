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

namespace Buckaroo\Magento2\Gateway\Request\CreditManagement;

use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Service\Culture\CultureCodeResolver;

class CompanyDataBuilder extends AbstractDataBuilder
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

        $billingAddress = $this->getOrder()->getBillingAddress();
        $company = $billingAddress->getCompany();

        if (empty($company)) {
            return [];
        }

        return [
            'culture' => $this->cultureCodeResolver->resolveDebtorCultureForOrder($this->getOrder()),
            'name'    => $billingAddress->getCompany() ?? 'Person'
        ];
    }
}
