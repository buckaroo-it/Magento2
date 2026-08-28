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

use Buckaroo\Magento2\Helper\StoreId;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Exception;
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
use Magento\Quote\Api\CartRepositoryInterface;
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
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PushUrlBuilder
     */
    protected $pushUrlBuilder;

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
    protected $clientInterface;

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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

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
     * @param CartRepositoryInterface $cartRepository
     * @param PushUrlBuilder $pushUrlBuilder
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
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
        PaymentGroupTransaction $groupTransaction,
        CartRepositoryInterface $cartRepository,
        PushUrlBuilder $pushUrlBuilder
    ) {
        $this->pushUrlBuilder = $pushUrlBuilder;
        $this->cartRepository = $cartRepository;
        $this->scopeConfig = $scopeConfig;
        $this->configProviderAccount = $configProviderAccount;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->storeManager = $storeManager;
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
        $this->httpRequest = $httpRequest;
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Send gift card request
     *
     * @throws GiftcardException
     * @throws \Exception
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
        } catch (ClientException|ConverterException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Get request body
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function getBody(): array
    {
        $incrementId = $this->getIncrementId();
        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);

        $ip = $this->getIp($this->getStoreId());
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
            "pushURL"         => $this->pushUrlBuilder->getPushUrl($this->getStoreId()),
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
     * @throws \Exception
     *
     * @return string
     */
    public function getIncrementId(): string
    {
        /** @var Quote $quote */
        $quote = $this->quote;
        if ($quote->getReservedOrderId() !== null) {
            return $quote->getReservedOrderId();
        }
        $quote->reserveOrderId();
        $this->cartRepository->save($quote);
        return $quote->getReservedOrderId();
    }

    /**
     * Get client IP
     *
     * @param null|int|string|StoreInterface $store
     *
     * @throws Exception
     *
     * @return false|string
     */
    protected function getIp($store)
    {
        if (!$this->httpRequest instanceof RequestInterface) {
            throw new Exception(
                __("Required parameter `httpRequest` must be instance of Magento\Framework\App\RequestInterface")
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
     * @throws LocalizedException
     *
     * @return string
     */
    protected function getReturnUrl(): string
    {
        return $this->urlBuilder->getRouteUrl(
            'buckaroo/redirect/process',
            ['_scope' => $this->getStoreId()]
        ) . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * Set voucherCode
     *
     * @param string $voucherCode
     *
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
     *
     * @return ApplyVoucherRequestInterface
     */
    public function setQuote(CartInterface $quote): ApplyVoucherRequestInterface
    {
        $this->quote = $quote;
        return $this;
    }

    /**
     * Get the id of the store the request has to be scoped to.
     *
     * The quote's store is authoritative: the ip_header setting and the return-URL scope both have
     * to match the store view the cart lives in, and this class is reachable from the REST API
     * where the ambient store is the default store view rather than the shopper's.
     *
     * @return int|null
     * @throws NoSuchEntityException
     */
    protected function getStoreId(): ?int
    {
        $quoteStoreId = $this->quote !== null ? StoreId::normalize($this->quote->getStoreId()) : null;

        if ($quoteStoreId !== null) {
            return $quoteStoreId;
        }

        return StoreId::normalize($this->storeManager->getStore());
    }
}
