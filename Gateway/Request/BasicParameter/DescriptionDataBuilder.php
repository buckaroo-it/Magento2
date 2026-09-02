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

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Payment\Gateway\Request\BuilderInterface;

class DescriptionDataBuilder implements BuilderInterface
{
    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * Constructor
     *
     * @param Account $configProviderAccount
     */
    public function __construct(
        Account $configProviderAccount
    ) {
        $this->configProviderAccount = $configProviderAccount;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $store = $order->getStore();

        return [
            'description' => $this->configProviderAccount->getParsedLabel($store, $order)
        ];
    }
}
