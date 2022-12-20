<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class CurrencyDataBuilder implements BuilderInterface
{
    private const CURRENCY = 'currency';

    /**
     * @var string
     */
    private string $currency;

    /**
     * @var array
     */
    private array $allowedCurrencies;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $this->setAllowedCurrencies($paymentDO->getPayment()->getMethodInstance());

        return [
            self::CURRENCY => $this->getCurrency($order)
        ];
    }

    /**
     * Get Currency
     *
     * @param Order|null $order
     * @return string
     * @throws Exception
     */
    public function getCurrency($order = null)
    {
        if (empty($this->currency)) {
            $this->setCurrency($order);
        }

        return $this->currency;
    }

    /**
     * Set Currency
     *
     * @param Order $order
     * @return $this
     * @throws Exception
     */
    public function setCurrency($order)
    {
        $allowedCurrencies = $this->getAllowedCurrencies();
        if (in_array($order->getOrderCurrencyCode(), $allowedCurrencies)) {
            $this->currency = $order->getOrderCurrencyCode();
        } elseif (in_array($order->getBaseCurrencyCode(), $allowedCurrencies)) {
            $this->currency = $order->getBaseCurrencyCode();
        } else {
            throw new \Buckaroo\Magento2\Exception(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }

        return $this;
    }

    /**
     * Get Allowed Currencies
     *
     * @param \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
     * @return array
     * @throws Exception
     */
    public function getAllowedCurrencies($methodInstance = null)
    {
        if (empty($this->allowedCurrencies)) {
            $this->setAllowedCurrencies($methodInstance);
        }

        return $this->allowedCurrencies;
    }

    /**
     * Set Allowed Currencies
     *
     * @param \Buckaroo\Magento2\Model\Method\BuckarooAdapter $methodInstance
     * @return $this
     * @throws \Buckaroo\Magento2\Exception
     */
    private function setAllowedCurrencies($methodInstance)
    {
//        $method = $methodInstance->getCode() ?? 'buckaroo_magento2_ideal';
//        $configProvider = $this->configProviderMethodFactory->get($method);
        $this->allowedCurrencies = ['EUR'];


        return $this;
    }
}
