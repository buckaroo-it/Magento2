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

namespace Buckaroo\Magento2\Model\Voucher;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyVoucherRequest implements ApplyVoucherRequestInterface
{
    /**
     * @var StoreInterface
     */
    protected $store;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var RequestInterface
     */
    protected $httpRequest;

    /**
     * @var CartInterface
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
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var string
     */
    protected $voucherCode;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Account $configProviderAccount
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param StoreManagerInterface $storeManager
     * @param SDKTransferFactory $transferFactory
     * @param ClientInterface $clientInterface
     * @param RequestInterface $httpRequest
     * @param PaymentGroupTransaction $groupTransaction
     * @throws NoSuchEntityException
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
     * Send gift card request
     *
     * @return mixed
     * @throws GiftcardException
     * @throws \Exception
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
        } catch (ClientException|ConverterException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Get request body
     *
     * @return array
     * @throws \Exception
     */
    protected function getBody(): array
    {
        $incrementId = $this->getIncrementId();
        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);

        $ip = $this->getIp($this->store);
        $body = [
            "payment_method"  => "buckaroovoucher",
            "currency"        => $this->getCurrency(),
            'amountDebit'     => $this->getAmount(),
            "invoice"         => $incrementId,
            "order"           => $incrementId,
            "returnURL"       => $this->getReturnUrl(),
            "returnURLCancel" => $this->getReturnUrl(),
            "returnURLError"  => $this->getReturnUrl(),
            "returnURLReject" => $this->getReturnUrl(),
            "pushURL"         => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'clientIP'        => [
                'address' => $ip !== false ? $ip : 'unknown',
                'type'    => strpos($ip, ':') === false ? '0' : '1',
            ],
            'vouchercode'     => $this->voucherCode
        ];
        if ($originalTransactionKey !== null) {
            $body['originalTransactionKey'] = $originalTransactionKey;
        }
        return $body;
    }

    /**
     * Get order increment id
     *
     * @return string
     * @throws \Exception
     */
    public function getIncrementId(): string
    {
        /** @var Quote $quote */
        $quote = $this->quote;
        if ($quote->getReservedOrderId() !== null) {
            return $quote->getReservedOrderId();
        }
        $quote->reserveOrderId()->save();
        return $quote->getReservedOrderId();
    }

    /**
     * Get client IP
     *
     * @param null|int|string|StoreInterface $store
     * @return false|string
     * @throws \Exception
     */
    protected function getIp($store)
    {
        if (!$this->httpRequest instanceof RequestInterface) {
            throw new \Exception(
                "Required parameter `httpRequest` must be instance of Magento\Framework\App\RequestInterface"
            );
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

    /**
     * Get Currency for giftcard
     *
     * @return string|null
     */
    protected function getCurrency(): ?string
    {
        $currency = $this->quote->getCurrency();
        if ($currency !== null) {
            return $currency->getBaseCurrencyCode();
        }

        return null;
    }

    /**
     * Get quote grand total
     *
     * @return float
     */
    protected function getAmount(): float
    {
        /** @var Quote $quote */
        $quote = $this->quote;
        return $quote->getGrandTotal();
    }

    /**
     * Get return url
     *
     * @return string
     * @throws LocalizedException
     */
    protected function getReturnUrl(): string
    {
        return $this->urlBuilder
                ->setScope($this->store->getId())
                ->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * Set voucherCode
     *
     * @param string $voucherCode
     * @return ApplyVoucherRequestInterface
     */
    public function setVoucherCode(string $voucherCode): ApplyVoucherRequestInterface
    {
        $this->voucherCode = trim($voucherCode);
        return $this;
    }

    /**
     * Set quote
     *
     * @param CartInterface $quote
     * @return ApplyVoucherRequestInterface
     */
    public function setQuote(CartInterface $quote): ApplyVoucherRequestInterface
    {
        $this->quote = $quote;
        return $this;
    }
}
