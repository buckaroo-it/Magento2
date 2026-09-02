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

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;

/**
 * Resolves the currency a Buckaroo transaction will be sent in for a given order
 * and payment method. Transactions are always sent in the order currency; when a
 * payment method does not support the order currency it must not be offered at
 * all (see AvailableBasedOnCurrencyValidator) instead of silently converting to
 * another currency. Used by the currency and amount data builders and the
 * availability validator so they all share the same rule.
 */
class TransactionCurrencyResolver
{
    /**
     * @var Factory
     */
    private $configProviderMethodFactory;

    /**
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(Factory $configProviderMethodFactory)
    {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * Resolve the transaction currency for the order/payment method combination.
     *
     * Returns the order currency when the payment method supports it, null otherwise.
     *
     * @param Order           $order
     * @param MethodInterface $methodInstance
     *
     * @throws Exception
     *
     * @return string|null
     */
    public function resolve(Order $order, MethodInterface $methodInstance): ?string
    {
        $orderCurrency = $order->getOrderCurrencyCode();

        return $this->isCurrencyAllowed($orderCurrency, $methodInstance) ? $orderCurrency : null;
    }

    /**
     * Whether the payment method can transact in the given currency.
     *
     * @param string|null     $currencyCode
     * @param MethodInterface $methodInstance
     *
     * @throws Exception
     *
     * @return bool
     */
    public function isCurrencyAllowed(?string $currencyCode, MethodInterface $methodInstance): bool
    {
        return $currencyCode !== null
            && in_array($currencyCode, $this->getAllowedCurrencies($methodInstance));
    }

    /**
     * Get the currencies the payment method is allowed to transact in.
     *
     * @param MethodInterface $methodInstance
     *
     * @throws Exception
     *
     * @return array
     */
    public function getAllowedCurrencies(MethodInterface $methodInstance): array
    {
        $method = $methodInstance->getCode();
        if (!$method) {
            throw new Exception(
                __("The payment method code it is not set.")
            );
        }

        return $this->configProviderMethodFactory->get($method)->getAllowedCurrencies();
    }
}
