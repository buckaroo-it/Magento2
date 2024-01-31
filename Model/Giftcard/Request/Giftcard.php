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

namespace Buckaroo\Magento2\Model\Giftcard\Request;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Helper\Data as HelperData;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\Encryptor;
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
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Giftcard implements GiftcardInterface
{

    public const TCS_ACQUIRER = 'tcs';
    public const FASHIONCHEQUE_ACQUIRER = 'fashioncheque';
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
     * @var ClientInterface
     */
    protected ClientInterface $clientInterface;
    /**
     * @var SDKTransferFactory
     */
    protected $transferFactory;
    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;
    /**
     * @var string
     */
    protected $cardId;
    /**
     * @var string
     */
    protected $cardNumber;
    /**
     * Card pin
     *
     * @var string
     */
    protected $pin;
    /**
     * Service action
     *
     * @var string
     */
    protected $action = 'Pay';
    /**
     * @var array
     */
    protected $cardTypes = [
        self::FASHIONCHEQUE_ACQUIRER => [
            'number' => 'fashionChequeCardNumber',
            'pin'    => 'fashionChequePin',
        ],
        self::TCS_ACQUIRER           => [
            'number' => 'tcsCardnumber',
            'pin'    => 'tcsValidationCode',
        ]
    ];
    /**
     * @var Encryptor $encryptor
     */
    private $encryptor;
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
     * @var GiftcardRepositoryInterface
     */
    private GiftcardRepositoryInterface $giftcardRepository;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Account $configProviderAccount
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Encryptor $encryptor
     * @param StoreManagerInterface $storeManager
     * @param SDKTransferFactory $transferFactory
     * @param ClientInterface $clientInterface
     * @param RequestInterface $httpRequest
     * @param PaymentGroupTransaction $groupTransaction
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Account $configProviderAccount,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Encryptor $encryptor,
        StoreManagerInterface $storeManager,
        SDKTransferFactory $transferFactory,
        ClientInterface $clientInterface,
        RequestInterface $httpRequest,
        PaymentGroupTransaction $groupTransaction,
        GiftcardRepositoryInterface $giftcardRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configProviderAccount = $configProviderAccount;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->encryptor = $encryptor;
        $this->store = $storeManager->getStore();
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
        $this->httpRequest = $httpRequest;
        $this->groupTransaction = $groupTransaction;
        $this->giftcardRepository = $giftcardRepository;
    }

    /**
     * Send giftcard request
     *
     * @return mixed
     * @throws GiftcardException
     */
    public function send()
    {
        if ($this->cardId === null) {
            throw new GiftcardException("Giftcard id is required");
        }
        if ($this->cardNumber === null) {
            throw new GiftcardException("Giftcard number is required");
        }
        if ($this->pin === null) {
            throw new GiftcardException("Giftcard pin is required");
        }
        if ($this->quote === null) {
            throw new GiftcardException("Quote is required");
        }

        $transferO = $this->transferFactory->create(
            $this->getBody()
        );

        try {
            $response = $this->clientInterface->placeRequest($transferO);
            return $response['object'] ?? [];
        } catch (ClientException $e) {
            throw new GiftcardException($e->getMessage());
        } catch (ConverterException $e) {
            throw new GiftcardException($e->getMessage());
        }
    }

    /**
     * Get Request Body
     *
     * @return array
     * @throws \Exception
     */
    protected function getBody()
    {
        $incrementId = $this->getIncrementId();
        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);
        if ($originalTransactionKey !== null) {
            $this->action = 'PayRemainder';
        }

        $ip = $this->getIp($this->store);
        $body = [
            "currency"                          => $this->getCurrency(),
            'amountDebit'                       => $this->getAmount(),
            "invoice"                           => $incrementId,
            "order"                             => $incrementId,
            "returnURL"                         => $this->getReturnUrl(),
            "returnURLCancel"                   => $this->getReturnUrl(),
            "returnURLError"                    => $this->getReturnUrl(),
            "returnURLReject"                   => $this->getReturnUrl(),
            "pushURL"                           => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'clientIP'                          => [
                'address' => $ip !== false ? $ip : 'unknown',
                'type'    => strpos($ip, ':') === false ? '0' : '1',
            ],
            $this->getParameterNameCardNumber() => $this->cardNumber,
            $this->getParameterNameCardPin()    => $this->pin,
            "name"                              => $this->cardId
        ];
        if ($originalTransactionKey !== null) {
            $body['originalTransactionKey'] = $originalTransactionKey;
        }
        $body['payment_method'] = 'giftcard';

        return $body;
    }

    /**
     * Get order increment id
     *
     * @return string
     * @throws \Exception
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
     * Get client IP
     *
     * @param null|int|string $store
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
            return $currency->getQuoteCurrencyCode();
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
     * Determine parameter name for Card number
     *
     * @return string
     */
    protected function getParameterNameCardNumber(): string
    {
        if ($this->getAcquirer() !== null) {
            return $this->cardTypes[$this->getAcquirer()]['number'];
        }

        if (isset($this->cardTypes[$this->cardId])) {
            return $this->cardTypes[$this->cardId]['number'];
        }

        if ($this->isCustom()) {
            return 'intersolveCardnumber';
        }

        return 'cardnumber';
    }

    /**
     * Check if is custom giftcard
     *
     * @return boolean
     */
    protected function isCustom(): bool
    {
        return stristr($this->cardId, 'customgiftcard') === false;
    }

    /**
     * Determine parameter name for Pin
     *
     * @return string
     */
    protected function getParameterNameCardPin(): string
    {
        if ($this->getAcquirer() !== null) {
            return $this->cardTypes[$this->getAcquirer()]['pin'];
        }

        if (isset($this->cardTypes[$this->cardId])) {
            return $this->cardTypes[$this->cardId]['pin'];
        }

        if ($this->isCustom()) {
            return 'intersolvePIN';
        }

        return 'pin';
    }

    /**
     * Set card number
     *
     * @param string $cardNumber
     * @return GiftcardInterface
     */
    public function setCardNumber(string $cardNumber): GiftcardInterface
    {
        $this->cardNumber = trim(preg_replace('/([\s-]+)/', '', $cardNumber));
        return $this;
    }

    /**
     * Set card pin
     *
     * @param string $pin
     * @return GiftcardInterface
     */
    public function setPin(string $pin): GiftcardInterface
    {
        $this->pin = trim($pin);
        return $this;
    }

    /**
     * Set card type
     *
     * @param string $cardId
     * @return GiftcardInterface
     */
    public function setCardId(string $cardId): GiftcardInterface
    {
        $this->cardId = $cardId;
        return $this;
    }

    /**
     * Set quote
     *
     * @param CartInterface $quote
     * @return GiftcardInterface
     */
    public function setQuote(CartInterface $quote): GiftcardInterface
    {
        $this->quote = $quote;
        return $this;
    }

    /**
     * Get merchant key for store
     *
     * @return string
     * @throws \Exception
     */
    protected function getMerchantKey(): string
    {
        return $this->encryptor->decrypt(
            $this->configProviderAccount->getMerchantKey($this->store)
        );
    }

    /**
     * Get merchant secret for store
     *
     * @return string
     * @throws \Exception
     */
    protected function getSecretKey(): string
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
    protected function getMode(): int
    {
        $active = $this->scopeConfig->getValue(
            'payment/buckaroo_magento2_giftcards/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return ($active == HelperData::MODE_LIVE) ? HelperData::MODE_LIVE : HelperData::MODE_TEST;
    }
}
