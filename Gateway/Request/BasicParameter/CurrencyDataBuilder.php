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
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;

class CurrencyDataBuilder implements BuilderInterface
{
    public const KEY_CURRENCY = 'currency';

    /**
     * @var Factory
     */
    private $configProviderMethodFactory;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var array
     */
    private $allowedCurrencies;

    /**
     * Constructor
     *
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Factory $configProviderMethodFactory
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
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
        $this->setAllowedCurrencies($paymentDO->getPayment()->getMethodInstance());

        return [
            self::KEY_CURRENCY => $this->getCurrency($order)
        ];
    }

    /**
     * Set Allowed Currencies
     *
     * @param  MethodInterface $methodInstance
     * @throws Exception
     * @return $this
     */
    private function setAllowedCurrencies(MethodInterface $methodInstance): CurrencyDataBuilder
    {
        $method = $methodInstance->getCode();
        if (!$method) {
            throw new Exception(
                __("The payment method code it is not set.")
            );
        }
        $configProvider = $this->configProviderMethodFactory->get($method);
        $this->allowedCurrencies = $configProvider->getAllowedCurrencies();

        return $this;
    }

    /**
     * Get Allowed Currencies
     *
     * @param  MethodInterface|null $methodInstance
     * @throws Exception
     * @return array
     */
    public function getAllowedCurrencies(?MethodInterface $methodInstance = null): array
    {
        if (empty($this->allowedCurrencies) && $methodInstance !== null) {
            $this->setAllowedCurrencies($methodInstance);
        }

        return $this->allowedCurrencies;
    }

    /**
     * Get Currency
     *
     * @param  Order|null $order
     * @throws Exception
     * @return string
     */
    public function getCurrency(?Order $order = null): string
    {
        if (empty($this->currency)) {
            $this->setCurrency($order);
        }

        return $this->currency;
    }

    /**
     * Set Currency
     *
     * @param  Order     $order
     * @throws Exception
     * @return $this
     */
    public function setCurrency(Order $order): CurrencyDataBuilder
    {
        $allowedCurrencies = $this->getAllowedCurrencies();
        if (in_array($order->getOrderCurrencyCode(), $allowedCurrencies)) {
            $this->currency = $order->getOrderCurrencyCode();
        } elseif (in_array($order->getBaseCurrencyCode(), $allowedCurrencies)) {
            $this->currency = $order->getBaseCurrencyCode();
        } else {
            throw new Exception(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }

        return $this;
    }
}
