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
