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
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use TIG\Buckaroo\Gateway\Http\Transaction;
use TIG\Buckaroo\Model\ConfigProvider\Account;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;

abstract class AbstractTransactionBuilder implements \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface
{
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
        if (empty($this->invoiceId)) {
            $order = $this->getOrder();
            $this->setInvoiceId($order->getIncrementId());
        }

        return $this->invoiceId;
    }

    /**
     * @param string $invoiceId
     *
     * @return $this
     */
    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

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
     * TransactionBuilder constructor.
     *
     * @param ScopeConfigInterface  $scopeConfig
     * @param SoftwareData          $softwareData
     * @param Account               $configProviderAccount
     * @param Transaction           $transaction
     * @param UrlInterface          $urlBuilder
     * @param FormKey               $formKey
     * @param null|int|float|double $amount
     * @param null|string           $currency
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SoftwareData $softwareData,
        Account $configProviderAccount,
        Transaction $transaction,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        $amount = null,
        $currency = null
    ) {
        $this->scopeConfig           = $scopeConfig;
        $this->softwareData          = $softwareData;
        $this->configProviderAccount = $configProviderAccount;
        $this->transaction           = $transaction;
        $this->urlBuilder            = $urlBuilder;
        $this->formKey               = $formKey;

        if ($amount !== null) {
            $this->amount = $amount;
        }

        if ($currency !== null) {
            $this->currency = $currency;
        }
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
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($this->order->getStoreId());
            $url = $url->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    /**
     * @return Transaction
     */
    public function build()
    {
        $transaction = $this->transaction->setBody($this->getBody());
        $transaction->setHeaders($this->getHeaders());
        $transaction->setMethod($this->getMethod());

        $store = $this->getOrder()->getStore();

        $transaction->setStore($store);

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
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->getOrder()->getStore();

        $localeCountry = $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $store);
        $localeCountry = str_replace('_', '-', $localeCountry);

        $headers[] = new \SoapHeader(
            'https://checkout.buckaroo.nl/PaymentEngine/',
            'MessageControlBlock',
            [
                'Id'                => '_control',
                'WebsiteKey'        => $this->configProviderAccount->getMerchantKey($store),
                'Culture'           => $localeCountry,
                'TimeStamp'         => time(),
                'Channel'           => $this->channel,
                'Software'          => $this->softwareData->get()
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
}
