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

namespace Buckaroo\Magento2\Model\Giftcard\Request;

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
use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class Giftcard implements GiftcardInterface
{

    public const TCS_ACQUIRER = 'tcs';
    public const FASHIONCHEQUE_ACQUIRER = 'fashioncheque';
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
     * Card id
     *
     * @var string
     */
    protected $cardId;

    /**
     * Card number
     *
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
     * Card types
     *
     * @var array
     */
    protected $cardTypes = [
        self::FASHIONCHEQUE_ACQUIRER => [
            'number' => 'FashionChequeCardNumber',
            'pin' => 'FashionChequePin',
        ],
        self::TCS_ACQUIRER => [
            'number' => 'TCSCardnumber',
            'pin' => 'TCSValidationCode',
        ]
    ];

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
     * @param Json $client
     * @param RequestInterface $httpRequest
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
        PaymentGroupTransaction $groupTransaction,
        GiftcardRepositoryInterface $giftcardRepository
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
        $this->giftcardRepository = $giftcardRepository;
    }
    /**
     * Send giftcard request
     *
     * @return mixed
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
                        "Name" => $this->cardId,
                        "Parameters" => [
                            [
                                "Name" => $this->getParameterNameCardNumber(),
                                "Value" => $this->cardNumber
                            ], [
                                "Name" => $this->getParameterNameCardPin(),
                                "Value" => $this->pin
                            ]
                        ]
                    ]
                ]
            ]
        ];
        if ($originalTransactionKey !== null) {
            $body['OriginalTransactionKey'] = $originalTransactionKey;
        }
        return $body;
    }
    /**
     * Set card number
     *
     * @param string $cardNumber
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setCardNumber(string $cardNumber)
    {
        $this->cardNumber = trim(preg_replace('/([\s-]+)/', '', $cardNumber));
        return $this;
    }
    /**
     * Set card pin
     *
     * @param string $pin
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setPin(string $pin)
    {
        $this->pin = trim($pin);
        return $this;
    }
    /**
     * Set card type
     *
     * @param string $cardId
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    public function setCardId(string $cardId)
    {
        $this->cardId = $cardId;
        return $this;
    }
    /**
     * Set quote
     *
     * @param CartInterface $quote
     *
     * @return \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
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
     * Get merchant key for store
     *
     * @return mixed
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
            'payment/buckaroo_magento2_giftcards/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return ($active == HelperData::MODE_LIVE) ? HelperData::MODE_LIVE : HelperData::MODE_TEST;
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
    /**
     * Determine parameter name for Card number
     *
     * @return string
     */
    protected function getParameterNameCardNumber()
    {
        if ($this->getAcquirer() !== null) {
            return $this->cardTypes[$this->getAcquirer()]['number'];
        }
        if (isset($this->cardTypes[$this->cardId])) {
            return $this->cardTypes[$this->cardId]['number'];
        }

        if ($this->isCustom()) {
            return 'IntersolveCardnumber';
        }

        return 'Cardnumber';
    }
    /**
     * Determine parameter name for Pin
     *
     * @return string
     */
    protected function getParameterNameCardPin()
    {
        if ($this->getAcquirer() !== null) {
            return $this->cardTypes[$this->getAcquirer()]['pin'];
        }

        if (isset($this->cardTypes[$this->cardId])) {
            return $this->cardTypes[$this->cardId]['pin'];
        }

        if ($this->isCustom()) {
            return 'IntersolvePin';
        }

        return 'Pin';
    }
    /**
     * Check if is custom giftcard
     *
     * @return boolean
     */
    protected function isCustom()
    {
        return stristr($this->cardId, 'customgiftcard') === false;
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

    private function getAcquirer()
    {
        return $this->giftcardRepository
            ->getByServiceCode($this->cardId)
            ->getAcquirer();
    }
}
