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

namespace Buckaroo\Magento2\Model\Voucher;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Gateway\Http\Client\Json;
use Buckaroo\Magento2\Helper\Data as HelperData;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;

class ApplyVoucherRequest implements ApplyVoucherRequestInterface
{
    protected $action = 'Pay';
    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var Encryptor $encryptor
     */
    private $encryptor;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $httpRequest;

    /**
     * @var \Magento\Quote\Api\Data\CartInterface
     */
    protected $quote;

    /**
     * @var \Buckaroo\Magento2\Gateway\Http\Client\Json
     */
    protected $client;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var string
     */
    protected $voucherCode;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var FormKey
     */
    private $formKey;


    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Account $configProviderAccount
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Encryptor $encryptor
     * @param StoreManagerInterface $storeManager
     * @param Json $client
     * @param RequestInterface $httpRequest
     * @param PaymentGroupTransaction $groupTransaction
     * @throws NoSuchEntityException
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Account $configProviderAccount,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Encryptor $encryptor,
        StoreManagerInterface $storeManager,
        Json $client,
        RequestInterface $httpRequest,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configProviderAccount = $configProviderAccount;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        $this->client = $client;
        $this->httpRequest = $httpRequest;
        $this->groupTransaction = $groupTransaction;
    }
    /**
     * Send giftcard request
     *
     * @return mixed
     */
    public function send()
    {
        if ($this->voucherCode === null) {
            throw new GiftcardException("Field `voucherCode` is required");
        }


        $this->client->setSecretKey($this->getSecretKey());
        $this->client->setWebsiteKey($this->getMerchantKey());

        return $this->client->doRequest($this->getBody(), $this->getMode());
    }
    /**
     * @return array
     */
    protected function getBody()
    {
        $incrementId =  $this->getIncrementId();
        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);
        if ($originalTransactionKey !== null) {
            $this->action = 'PayRemainder';
        }

        $ip = $this->getIp($this->store);
        $body = [
            "Currency" => $this->getCurrency(),
            'AmountDebit' => $this->getAmount(),
            "Invoice" => $incrementId,
            "ReturnURL" => $this->getReturnUrl(),
            "ReturnURLCancel" => $this->getReturnUrl(),
            "ReturnURLError" => $this->getReturnUrl(),
            "ReturnURLReject" => $this->getReturnUrl(),
            "PushURL" => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'ClientIP' => (object)[
                'Address' => $ip !== false ? $ip : 'unknown',
                'Type' => strpos($ip, ':') === false ? '0' : '1',
            ],
            "Services" => [
                "ServiceList" => [
                    [
                        "Action" => $this->action,
                        "Name" => "buckaroovoucher",
                        "Parameters" => [
                            [
                                "Name" => 'vouchercode',
                                "Value" => $this->voucherCode,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        if ($originalTransactionKey !== null) {
            $body['OriginalTransactionKey'] = $originalTransactionKey;
        }
        return $body;
    }

    /**
     * Set voucherCode
     *
     * @param string $voucherCode
     *
     * @return \Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface
     */
    public function setVoucherCode(string $voucherCode)
    {
        $this->voucherCode = trim($voucherCode);
        return $this;
    }

    /**
     * Set quote
     *
     * @param CartInterface $quote
     *
     * @return \Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface
     */
    public function setQuote(CartInterface $quote)
    {
        $this->quote = $quote;
        return $this;
    }
    /**
     * Get order increment id
     *
     * @return string
     */
    public function getIncrementId()
    {
        /**@var Quote */
        $quote = $this->quote;
        if ($quote->getReservedOrderId() !== null) {
            return $quote->getReservedOrderId();
        }
        $quote->reserveOrderId()->save();
        return $quote->getReservedOrderId();
    }
    /**
     * Get quote grand total
     *
     * @return float
     */
    protected function getAmount()
    {
        /**@var Quote */
        $quote = $this->quote;
        return $quote->getGrandTotal();
    }
    protected function getCurrency()
    {
        $currency = $this->quote->getCurrency();
        if ($currency !== null) {
            return $currency->getBaseCurrencyCode();
        }
    }

    /**
     * Get merchant key for store
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getMerchantKey()
    {
        return $this->encryptor->decrypt(
            $this->configProviderAccount->getMerchantKey($this->store)
        );
    }

    /**
     * Get merchant secret for store
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getSecretKey()
    {
        return $this->encryptor->decrypt(
            $this->configProviderAccount->getSecretKey($this->store)
        );
    }

    /**
     * Get request mode
     *
     * @return int
     */
    protected function getMode()
    {
        $active = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_voucher/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return ($active == HelperData::MODE_LIVE) ? HelperData::MODE_LIVE : HelperData::MODE_TEST;
    }

    /**
     * Get return url
     * @return string
     * @throws LocalizedException
     */
    protected function getReturnUrl()
    {
        return $this->urlBuilder
            ->setScope($this->store->getId())
            ->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->formKey->getFormKey();
    }

    protected function getIp($store)
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
