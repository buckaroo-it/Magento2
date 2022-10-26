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

use Magento\Quote\Model\Quote;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Payment\Gateway\Http\ConverterException;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface;

class ApplyVoucherRequest implements ApplyVoucherRequestInterface
{

    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

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
     * @var SDKTransferFactory
     */
    protected $transferFactory;

    /**
     * @var ClientInterface
     */
    protected ClientInterface $clientInterface;

    /**
     * @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var string
     */
    protected $voucherCode;


    /**
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param StoreManagerInterface $storeManager
     * @param SDKTransferFactory $transferFactory
     * @param ClientInterface $clientInterface
     * @param RequestInterface $httpRequest
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Account $configProviderAccount,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        StoreManagerInterface $storeManager,
        SDKTransferFactory $transferFactory,
        ClientInterface $clientInterface,
        RequestInterface $httpRequest,
        PaymentGroupTransaction $groupTransaction
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configProviderAccount = $configProviderAccount;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->store = $storeManager->getStore();
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
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

        $transferO = $this->transferFactory->create(
            $this->getBody()
        );

        try {
            $response = $this->clientInterface->placeRequest($transferO);
            return $response['object'] ?? [];
        } catch (ClientException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        } catch (ConverterException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        }
    }
    /**
     * @return array
     */
    protected function getBody()
    {
        $incrementId =  $this->getIncrementId();
        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);

        $ip = $this->getIp($this->store);
        $body = [
            "payment_method" => "buckaroovoucher",
            "currency" => $this->getCurrency(),
            'amountDebit' => $this->getAmount(),
            "invoice" => $incrementId,
            "order" => $incrementId,
            "returnURL" => $this->getReturnUrl(),
            "returnURLCancel" => $this->getReturnUrl(),
            "returnURLError" => $this->getReturnUrl(),
            "returnURLReject" => $this->getReturnUrl(),
            "pushURL" => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'clientIP' => [
                'address' => $ip !== false ? $ip : 'unknown',
                'type' => strpos($ip, ':') === false ? '0' : '1',
            ],
            'vouchercode' => $this->voucherCode
        ];
        if ($originalTransactionKey !== null) {
            $body['originalTransactionKey'] = $originalTransactionKey;
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
        if ($currency !== null)  return $currency->getBaseCurrencyCode();
    }
    /**
     * Get return url
     * @return string
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
