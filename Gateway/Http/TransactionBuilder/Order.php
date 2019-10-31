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

namespace TIG\Buckaroo\Gateway\Http\TransactionBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\UrlInterface;
use TIG\Buckaroo\Gateway\Http\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;

class Order extends AbstractTransactionBuilder
{
    /** @var RemoteAddress */
    protected $remoteAddress;

    /** @var Factory */
    protected $configProviderMethodFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SoftwareData         $softwareData
     * @param Account              $configProviderAccount
     * @param Transaction          $transaction
     * @param UrlInterface         $urlBuilder
     * @param RemoteAddress        $remoteAddress
     * @param Factory              $configProviderMethodFactory
     * @param FormKey              $formKey
     * @param null                 $amount
     * @param null                 $currency
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SoftwareData $softwareData,
        Account $configProviderAccount,
        Transaction $transaction,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        RemoteAddress $remoteAddress,
        Factory $configProviderMethodFactory,
        $amount = null,
        $currency = null
    ) {
        parent::__construct($scopeConfig, $softwareData, $configProviderAccount, $transaction, $urlBuilder, $formKey, $amount, $currency);

        $this->remoteAddress = $remoteAddress;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        if (!$this->getCurrency()) {
            $this->setOrderCurrency();
        }

        if ($this->getAmount() < 0.01) {
            $this->setOrderAmount();
        }

        $creditAmount = 0;
        if ($this->getType() == 'void') {
            $creditAmount = $this->getAmount();
            $this->setAmount(0);
        }

        $order = $this->getOrder();
        $store = $order->getStore();

        if ($this->configProviderAccount->getCreateOrderBeforeTransaction($store)) {
            $newStatus = $this->configProviderAccount->getOrderStatusNew($store);
            $orderState = 'new';
            if (!$newStatus) {
                $newStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }

            $order->setState($orderState);
            $order->setStatus($newStatus);
            $order->save();
        }

        $ip = $order->getRemoteIp();
        if (!$ip) {
            $ip = $this->remoteAddress->getRemoteAddress();
        }

        // Some of the plaza gateway requests do not support IPv6.
        if (strpos($ip, ':') !== false) {
            $ip = '127.0.0.' . rand(1, 100);
        }

        $body = [
            'Currency' => $this->getCurrency(),
            'AmountDebit' => $this->getAmount(),
            'AmountCredit' => $creditAmount,
            'Invoice' => $this->getInvoiceId(),
            'Order' => $order->getIncrementId(),
            'Description' => $this->configProviderAccount->getTransactionLabel($store),
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

        $body = $this->filterBody($body);

        $customVars = $this->getCustomVars();
        if (is_array($customVars) && count($customVars) > 0) {
            foreach ($customVars as $key => $val) {
                $body[$key] = $val;
            }
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
            $parameterLine[] = $this->getParameterLine('service_action_from_magento', strtolower($this->getServices()['Action']));
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

    /**
     * @param array $body
     *
     * @return array
     */
    private function filterBody($body)
    {
        if ($this->getMethod() == 'CancelTransaction') {
            $body['Transaction'] = ['Key' => $body['OriginalTransactionKey']];
            unset($body['OriginalTransactionKey']);
        }

        $services = $this->getServices();

        if (!isset($services['Name']) || !isset($services['Action'])) {
            return $body;
        }

        if (($services['Name'] == 'paymentguarantee' && $services['Action'] == 'Order') ||
            ($services['Name'] == 'emandate' && $this->getMethod() == 'DataRequest')
        ) {
            unset($body['Invoice']);
        }

        if (($services['Name'] == 'paymentguarantee' && $services['Action'] == 'PartialInvoice') ||
            ($services['Name'] == 'klarna' && $services['Action'] == 'Pay')
        ) {
            unset($body['OriginalTransactionKey']);
        }

        if (($services['Name'] == 'capayable' && $services['Action'] == 'PayInInstallments')) {
            unset($body['Order']);
        }

        if ($services['Name'] == 'CreditManagement3' && $services['Action'] == 'CreateCreditNote') {
            unset($body['AmountCredit']);
            unset($body['OriginalTransactionKey']);
        }

        return $body;
    }

    /**
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    private function getAllowedCurrencies()
    {
        /**
         * @var \TIG\Buckaroo\Model\Method\AbstractMethod $methodInstance
         */
        $methodInstance = $this->order->getPayment()->getMethodInstance();
        $method = $methodInstance->buckarooPaymentMethodCode;

        $configProvider = $this->configProviderMethodFactory->get($method);
        return $configProvider->getAllowedCurrencies();
    }

    /**
     * @return $this
     */
    private function setOrderAmount()
    {
        if ($this->getCurrency() == $this->order->getOrderCurrencyCode()) {
            return $this->setAmount($this->order->getGrandTotal());
        }

        return $this->setAmount($this->order->getBaseGrandTotal());
    }

    /**
     * @return $this
     * @throws \TIG\Buckaroo\Exception
     */
    private function setOrderCurrency()
    {
        if (in_array($this->order->getOrderCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getOrderCurrencyCode());
        }

        if (in_array($this->order->getBaseCurrencyCode(), $this->getAllowedCurrencies())) {
            return $this->setCurrency($this->order->getBaseCurrencyCode());
        }

        throw new \TIG\Buckaroo\Exception(
            __("The selected payment method does not support the selected currency or the store's base currency.")
        );
    }
}
