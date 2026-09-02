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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CurrencyDataBuilder implements BuilderInterface
{
    public const KEY_CURRENCY = 'currency';

    /**
     * @var TransactionCurrencyResolver
     */
    private $transactionCurrencyResolver;

    /**
     * Constructor
     *
     * @param TransactionCurrencyResolver $transactionCurrencyResolver
     */
    public function __construct(
        TransactionCurrencyResolver $transactionCurrencyResolver
    ) {
        $this->transactionCurrencyResolver = $transactionCurrencyResolver;
    }

    /**
     * @inheritdoc
     *
     * @throws Exception|LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        $currency = $this->transactionCurrencyResolver->resolve(
            $order,
            $paymentDO->getPayment()->getMethodInstance()
        );

        if ($currency === null) {
            throw new Exception(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }

        return [
            self::KEY_CURRENCY => $currency
        ];
    }
}
