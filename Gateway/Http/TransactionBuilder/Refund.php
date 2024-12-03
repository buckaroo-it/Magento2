<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Gateway\Http\TransactionBuilder;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;

class Refund extends AbstractTransactionBuilder
{

    /**
     * @return void
     * @throws Exception
     * @throws LocalizedException
     */
    protected function setRefundCurrencyAndAmount()
    {
        /**
         * @var AbstractMethod $methodInstance
         */
        $methodInstance = $this->order->getPayment()->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode;

        $configProvider = $this->configProviderMethodFactory->get($method);
        $allowedCurrencies = $configProvider->getAllowedCurrencies($this->order->getStore());

        if (in_array($this->order->getOrderCurrencyCode(), $allowedCurrencies)) {
            /**
             * @todo find a way to fix the cumulative rounding issue that occurs in creditmemos.
             *       This problem occurs when the creditmemo is being refunded in the order's currency, rather than the
             *       store's base currency.
             */
            $this->setCurrency($this->order->getOrderCurrencyCode());
            $this->setAmount(round($this->getAmount() * $this->order->getBaseToOrderRate(), 2));
        } elseif (in_array($this->order->getBaseCurrencyCode(), $allowedCurrencies)) {
            $this->setCurrency($this->order->getBaseCurrencyCode());
        } else {
            throw new \Buckaroo\Magento2\Exception(
                __("The selected payment method does not support the selected currency or the store's base currency.")
            );
        }
    }

    /**
     * @return array
     * @throws Exception
     * @throws LocalizedException
     */
    public function getBody()
    {
        if (!$this->getCurrency()) {
            $this->setRefundCurrencyAndAmount();
        }

        $order = $this->getOrder();
        $store = $order->getStore();

        $ip = $this->getIp($order);

        $body = [
            'Currency' => $this->getCurrency(),
            'AmountDebit' => 0,
            'AmountCredit' => $this->getAmount(),
            'Invoice' => $this->getInvoiceId(),
            'Order' => $order->getIncrementId(),
            'Description' => $this->configProviderAccount->getParsedLabel($store, $order),
            'ClientIP' => (object)[
                '_' => $ip,
                'Type' => strpos($ip, ':') === false ? 'IPv4' : 'IPv6',
            ],
            'ReturnURL' => $this->getReturnUrl(),
            'ReturnURLCancel' => $this->getReturnUrl(),
            'ReturnURLError' => $this->getReturnUrl(),
            'ReturnURLReject' => $this->getReturnUrl(),
            'OriginalTransactionKey' => $this->originalTransactionKey,
            'StartRecurrent' => $this->startRecurrent,
            'PushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'Services' => (object)[
                'Service' => $this->getServices()
            ],
            'AdditionalParameters' => (object)[
                'AdditionalParameter' => $this->getAdditionalParameters()
            ],
        ];

        if ($this->order->getTotalRefunded() >= $this->order->getGrandTotal()) {
            $this->order->setState(\Magento\Sales\Model\Order::STATE_CLOSED)
                ->setStatus('closed');
            $this->order->save();
        }

        return $body;
    }

    /**
     * @return array
     */
    private function getAdditionalParameters()
    {
        $parameterLine = [];
        if (isset($this->getServices()['Action'])) {
            $parameterLine[] = $this->getParameterLine(
                'service_action_from_magento',
                strtolower($this->getServices()['Action'])
            );
        }

        $parameterLine[] = $this->getParameterLine('initiated_by_magento', 1);

        return $parameterLine;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return array
     */
    private function getParameterLine($name, $value)
    {
        $line = [
            '_'    => $value,
            'Name' => $name,
        ];

        return $line;
    }
}
