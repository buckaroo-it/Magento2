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

use Magento\Store\Model\Store;
use Magento\Framework\App\RequestInterface;
use Buckaroo\Magento2\Model\GroupTransaction;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;



class RefundPartial extends AbstractTransactionBuilder
{

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $httpRequest;


    /**
     * @var \Magento\Store\Model\Store
     */
    protected $store;


    /**
     *
     * @var \Buckaroo\Magento2\Model\GroupTransaction
     */
    protected $groupTransaction;

    /**
     * Set giftcard transaction
     *
     * @param GroupTransaction $groupTransaction
     *
     * @return self
     */
    public function setGroupTransaction(GroupTransaction $groupTransaction)
    {
        $this->groupTransaction = $groupTransaction;
        return $this;
    }

    /**
     * Set originating http request
     *
     * @param RequestInterface $request
     *
     * @return self
     */
    public function setRequest(RequestInterface $request)
    {
        $this->httpRequest = $request;
        return $this;
    }

    /**
     * Set current store
     *
     * @param Store $store
     *
     * @return self
     */
    public function setStore(Store $store)
    {
        $this->store = $store;
        return $this;
    }
    /**
     * @return array
     */
    public function getBody()
    {
        if (!$this->store instanceof Store) {
            throw new \Exception("`store` must be instance of Magento\Store\Model\Store");
        }


        if (!$this->groupTransaction instanceof GroupTransaction) {
            throw new \Exception("`groupTransaction` must be instance of Buckaroo\Magento2\Model\GroupTransaction");
        }

        $ip = $this->getUserIp($this->store);

        $body = [
            'Currency' => $this->groupTransaction->getCurrency(),
            'AmountDebit' => 0,
            'AmountCredit' => $this->groupTransaction->getRemainingAmount(),
            'Invoice' => $this->groupTransaction->getOrderIncrementId(),
            'Order' => $this->groupTransaction->getOrderIncrementId(),
            'Description' => $this->configProviderAccount->getTransactionLabel($this->store),
            'ClientIP' => (object)[
                '_' => $ip,
                'Type' => strpos($ip, ':') === false ? 'IPv4' : 'IPv6',
            ],
            'ReturnURL' => $this->getReturnUrl(),
            'ReturnURLCancel' => $this->getReturnUrl(),
            'ReturnURLError' => $this->getReturnUrl(),
            'ReturnURLReject' => $this->getReturnUrl(),
            'OriginalTransactionKey' => $this->groupTransaction->getTransactionId(),
            'StartRecurrent' => $this->startRecurrent,
            'PushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'Services' => (object)[
                'Service' => [
                    'Name'             =>  $this->groupTransaction->getServicecode(),
                    'Action'           => 'Refund',
                ]
            ],
            'AdditionalParameters' => (object)[
                'AdditionalParameter' => $this->getAdditionalParameters()
            ],
        ];

        return $body;
    }

    /**
     * Get merchant key for store
     *
     * @return mixed
     */
    public function getMerchantKey()
    {
        return $this->encryptor->decrypt(
            $this->configProviderAccount->getMerchantKey($this->store)
        );
    }
    /**
     * @return array
     */
    private function getAdditionalParameters()
    {
        return [
            $this->getParameterLine('service_action_from_magento', 'refund'),
            $this->getParameterLine('initiated_by_magento', 1)
        ];
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
     * {@inheritdoc}
     */
    public function getReturnUrl()
    {
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($this->store->getId());
            $url = $url->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }
    /**
     * Get user ip
     *
     * @param Store $store
     *
     * @return string
     * @throws \Exception
     */
    protected function getUserIp($store)
    {
        
        if (!$this->httpRequest instanceof RequestInterface) {
            throw new \Exception("Required parameter `httpRequest` must be instance of Magento\Framework\App\RequestInterface");
        }

        $ipHeaders = $this->configProviderAccount->getIpHeader($store);

        $headers = [];
        if ($ipHeaders) {
            $ipHeaders = explode(',', strtoupper($ipHeaders));
            foreach ($ipHeaders as $ipHeader) {
                $headers[] = 'HTTP_' . str_replace('-', '_', $ipHeader);
            }
        }

        $remoteAddress = new RemoteAddress(
            $this->httpRequest,
            $headers
        );

        return $remoteAddress->getRemoteAddress();
    }
}
