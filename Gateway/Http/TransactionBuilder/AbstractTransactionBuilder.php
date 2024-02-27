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

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Gateway\Http\Transaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

abstract class AbstractTransactionBuilder implements \Buckaroo\Magento2\Gateway\Http\TransactionBuilderInterface
{

    public const ADDITIONAL_RETURN_URL = 'buckaroo_return_url';

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var array
     */
    protected $services;

    /**
     * @var array
     */
    protected $customVars;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var bool|string
     */
    protected $type = false;

    /**
     * @var null|string
     */
    protected $returnUrl = null;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var SoftwareData
     */
    protected $softwareData;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @var Transaction
     */
    protected $transaction;

    /** @var UrlInterface */
    protected $urlBuilder;

    /**
     * @var bool
     */
    protected $startRecurrent = false;

    /**
     * @var null|string
     */
    protected $originalTransactionKey = null;

    /**
     * @var null|string
     */
    protected $channel = 'Web';

    /** @var FormKey */
    private $formKey;

    private $additionaParameters;

    /**
     * @var int
     */
    public $amount;

    /**
     * @var string
     */
    public $currency;

    /**
     * @var string
     */
    public $invoiceId;

    private $isCustomInvoiceId = false;

    /** @var Encryptor $encryptor */
    protected $encryptor;

    /** @var Factory */
    protected $configProviderMethodFactory;

    /**
     * @var Log $logging
     */
    public $logging;

    protected $merchantKey;

    /**
     * TransactionBuilder constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param SoftwareData $softwareData
     * @param Account $configProviderAccount
     * @param Transaction $transaction
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Encryptor $encryptor
     * @param AbstractMethod $abstractMethod
     * @param Factory $configProviderMethodFactory
     * @param null|int|float|double $amount
     * @param null|string $currency
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SoftwareData $softwareData,
        Account $configProviderAccount,
        Transaction $transaction,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Encryptor $encryptor,
        Factory $configProviderMethodFactory,
        Log $logging,
        $amount = null,
        $currency = null
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->softwareData = $softwareData;
        $this->configProviderAccount = $configProviderAccount;
        $this->transaction = $transaction;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->encryptor = $encryptor;
        $this->logging = $logging;

        if ($amount !== null) {
            $this->amount = $amount;
        }

        if ($currency !== null) {
            $this->currency = $currency;
        }

        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function setOriginalTransactionKey($originalTransactionKey)
    {
        $this->originalTransactionKey = $originalTransactionKey;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getOriginalTransactionKey()
    {
        return $this->originalTransactionKey;
    }

    /**
     * {@inheritdoc}
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param boolean $startRecurrent
     *
     * @return $this
     */
    public function setStartRecurrent($startRecurrent)
    {
        $this->startRecurrent = $startRecurrent;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     *
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return int
     */
    public function getInvoiceId()
    {
        $order = $this->getOrder();

        if (empty($this->invoiceId)
            ||
            (!$this->isCustomInvoiceId && ($this->invoiceId != $order->getIncrementId()))
        ) {
            $this->setInvoiceId($order->getIncrementId(), false);
        }

        return $this->invoiceId;
    }

    /**
     * @param string $invoiceId
     *
     * @return $this
     */
    public function setInvoiceId($invoiceId, $isCustomInvoiceId = true)
    {
        $this->invoiceId = $invoiceId;
        $this->isCustomInvoiceId = $isCustomInvoiceId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     *
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * {@inheritdoc}
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }

    /**
     * {@inheritdoc}
     */
    public function setServices($services)
    {
        $this->services = $services;

        return $this;
    }

    /**
     * @return array
     */
    public function getCustomVars()
    {
        return $this->customVars;
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomVars($customVars)
    {
        $this->customVars = $customVars;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setReturnUrl($url)
    {
        $routeUrl = $this->urlBuilder->getRouteUrl($url);

        $this->returnUrl = $routeUrl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnUrl()
    {
        
        $returnUrl = $this->getReturnUrlFromPayment();
        if($returnUrl !== null) {
            $this->setReturnUrl($returnUrl);
            return $returnUrl;
        }

        
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($this->order->getStoreId());
            $url = $url->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    public function getReturnUrlFromPayment()
    {
        if(
            $this->getOrder() === null || 
            $this->getOrder()->getPayment() === null ||
            $this->getOrder()->getPayment()->getAdditionalInformation(self::ADDITIONAL_RETURN_URL) === null
        ) {
            return;
        }
        $returnUrl = $this->getOrder()->getPayment()->getAdditionalInformation(self::ADDITIONAL_RETURN_URL);
        if(
            !filter_var($returnUrl, FILTER_VALIDATE_URL) === false && 
            in_array(parse_url($returnUrl, PHP_URL_SCHEME), ['http','https'])
        ) {
            return $returnUrl;
        }
    }

    /**
     * @return Transaction
     */
    public function build()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $transaction = $this->transaction->setBody($this->getBody());
        $transaction->setHeaders($this->getHeaders());
        $transaction->setMethod($this->getMethod());

        if ($this->getOrder()) {
            $store = $this->getOrder()->getStore();
            $transaction->setStore($store);
        }

        return $transaction;
    }

    /**
     * @return array
     */
    abstract public function getBody();

    /**
     * @returns array
     */
    public function getHeaders()
    {
        if ($this->getOrder() && ($store = $this->getOrder()->getStore())) {
            $localeCountry = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $store);
            $localeCountry = str_replace('_', '-', $localeCountry);

            $merchantKey = $this->encryptor->decrypt($this->configProviderAccount->getMerchantKey($store));

            $payment = $this->getOrder()->getPayment();
        }

        $headers[] = new \SoapHeader(
            'https://checkout.buckaroo.nl/PaymentEngine/',
            'MessageControlBlock',
            [
                'Id' => '_control',
                'WebsiteKey' => $merchantKey ?? $this->getMerchantKey(),
                'Culture' => $localeCountry ?? 'en-US',
                'TimeStamp' => time(),
                'Channel' => $this->channel,
                'Software' => $this->softwareData->get($payment ?? null)
            ],
            false
        );

        $headers[] = new \SoapHeader(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'Security',
            [
                'Signature' => [
                    'SignedInfo' => [
                        'CanonicalizationMethod' => [
                            'Algorithm' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
                        ],
                        'SignatureMethod' => [
                            'Algorithm' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
                        ],
                        'Reference' => [
                            [
                                'Transforms' => [
                                    [
                                        'Algorithm' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
                                    ]
                                ],
                                'DigestMethod' => [
                                    'Algorithm' => 'http://www.w3.org/2000/09/xmldsig#sha1',
                                ],
                                'DigestValue' => '',
                                'URI' => '#_body',
                                'Id' => null,
                            ],
                            [
                                'Transforms' => [
                                    [
                                        'Algorithm' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
                                    ]
                                ],
                                'DigestMethod' => [
                                    'Algorithm' => 'http://www.w3.org/2000/09/xmldsig#sha1',
                                ],
                                'DigestValue' => '',
                                'URI' => '#_control',
                                'Id' => null,
                            ]
                        ]
                    ],
                    'SignatureValue' => '',
                ],
                'KeyInfo' => ' ',
            ],
            false
        );

        return $headers;
    }

    protected function getIp($order)
    {
        $ip = $order->getRemoteIp();
        $store = $order->getStore();

        $ipHeaders = $this->configProviderAccount->getIpHeader($store);

        if ($ipHeaders) {
            $ipHeaders = explode(',', (string)strtoupper($ipHeaders));
            foreach ($ipHeaders as &$ipHeader) {
                $ipHeader = 'HTTP_' . str_replace('-', '_', (string)$ipHeader);
            }
            $ip = $order->getPayment()->getMethodInstance()->getRemoteAddress(false, $ipHeaders);
        }

        //trustly anyway should be w/o private ip
        if ((isset($order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode) &&
                $order->getPayment()->getMethodInstance()->buckarooPaymentMethodCode == 'trustly'
            )
            &&
            $this->isIpPrivate($ip)
            &&
            $order->getXForwardedFor()
        ) {
            $ip = $order->getXForwardedFor();
        }

        if (!$ip) {
            $ip = $order->getPayment()->getMethodInstance()->getRemoteAddress();
        }

        return $ip;
    }

    private function isIpPrivate($ip)
    {
        if (!$ip) {
            return false;
        }

        $pri_addrs =  [
            '10.0.0.0|10.255.255.255', // single class A network
            '172.16.0.0|172.31.255.255', // 16 contiguous class B network
            '192.168.0.0|192.168.255.255', // 256 contiguous class C network
            '169.254.0.0|169.254.255.255', // Link-local address also referred to as Automatic Private IP Addressing
            '127.0.0.0|127.255.255.255' // localhost
        ];

        $long_ip = ip2long($ip);
        if ($long_ip != -1) {

            foreach ($pri_addrs as $pri_addr) {
                list ($start, $end) = explode('|', $pri_addr);

                if ($long_ip >= ip2long($start) && $long_ip <= ip2long($end)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function setAdditionalParameter($key, $value)
    {
        $this->additionaParameters[$key] = $value;

        return $this;
    }

    public function getAdditionalParameter($key)
    {
        return $this->additionaParameters[$key];
    }
    
    public function getAllAdditionalParameters()
    {
        return $this->additionaParameters;
    }

    public function setMerchantKey($merchantKey)
    {
        $this->merchantKey = $merchantKey;

        return $this;
    }

    public function getMerchantKey()
    {
        return $this->merchantKey;
    }
}
