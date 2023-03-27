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
use Buckaroo\Magento2\Model\ConfigProvider\Predefined;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Model\StoreManagerInterface;
use Buckaroo\Magento2\Gateway\Http\Transaction;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;

class Idin extends AbstractTransactionBuilder implements IdinBuilderInterface
{

    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;

    /**
     * @var mixed
     */
    protected $customerId;

    /**
     *
     * @var string
     */
    protected $issuer;

    /** @var Encryptor $encryptor */
    private $encryptor;

    /**
     * @var Account
     */
    protected $configProviderAccount;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SoftwareData $softwareData
     * @param Account $configProviderAccount
     * @param Transaction $transaction
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Encryptor $encryptor
     * @param Factory $configProviderMethodFactory
     * @param Log $logging
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
        Predefined $configProviderPredefined,
        Log $logging,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession
    ) {
        $this->store = $storeManager->getStore();
        $this->customerId =  $customerSession->getCustomerId();
        $this->encryptor = $encryptor;
        $this->configProviderAccount = $configProviderAccount;

        parent::__construct(
            $scopeConfig,
            $softwareData,
            $configProviderAccount,
            $transaction,
            $urlBuilder,
            $formKey,
            $encryptor,
            $configProviderMethodFactory,
            $configProviderPredefined,
            $logging
        );
    }

    /**
     * Set issuer 
     *
     * @param string $issuer
     *
     * @return $this
     */
    public function setIssuer(string $issuer)
    {
        $this->issuer = $issuer;
        return $this;
    }
    /**
     * @return array
     */
    public function getBody()
    {
        $additionalParameter = array_merge(
            [
                [
                    '_'    =>  $this->customerId,
                    'Name' => 'idin_cid',
                ]
            ],
            $this->getAdditionalFormattedParameters()
        );

        $body = [
            'ReturnURL' => $this->getReturnUrl(),
            'Services' => (object)[
                'Service' => [
                    'Name'             => 'Idin',
                    'Action'           => 'verify',
                    'Version'          => 0,
                    'RequestParameter' => [
                        [
                            '_'    => trim($this->issuer),
                            'Name' => 'issuerId',
                        ],
                    ],
                ]
            ],
            'AdditionalParameters' => (object)[
                'AdditionalParameter' => $additionalParameter
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
     * Get idin mode 
     *
     * @return int
     */
    public function getMode()
    {
        return $this->configProviderAccount->getIdin($this->store);
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
    /** @inheritDoc */
    public function build()
    {
        if ($this->issuer === null) {
            throw new \Exception("Issuer is required in idin transaction builder", 1);
        }
        $this->setMethod('DataRequest');

        return parent::build();
    }
    /**
     * Get any additional formatted parameters
     *
     * @return void
     */
    private function getAdditionalFormattedParameters()
    {
        $parameters = [];

        if (!is_array($this->getAllAdditionalParameters())) {
            return $parameters;
        }
        
        foreach ($this->getAllAdditionalParameters() as $key => $value) {
            $parameters[] = [
                '_'    =>  $value,
                'Name' =>  $key,
            ];
        }
        return $parameters;
    }
}
