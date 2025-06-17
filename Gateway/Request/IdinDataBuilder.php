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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Model\Config\Source\Enablemode;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class IdinDataBuilder implements BuilderInterface
{
    /**
     * @var UrlInterface
     */
    protected UrlInterface $urlBuilder;

    /**
     * @var StoreInterface
     */
    protected StoreInterface $store;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var null|string
     */
    private ?string $returnUrl = null;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @var Account
     */
    private Account $configProviderAccount;

    /**
     * @param CustomerSession $customerSession
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param StoreManagerInterface $storeManager
     * @param Account $configProviderAccount
     * @throws NoSuchEntityException
     */
    public function __construct(
        CustomerSession $customerSession,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        StoreManagerInterface $storeManager,
        Account $configProviderAccount
    ) {
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->store = $storeManager->getStore();
        $this->configProviderAccount = $configProviderAccount;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $returnUrl = $this->getReturnUrl();

        return [
            'payment_method'       => 'idin',
            'returnURL'            => $returnUrl,
            'returnURLError'       => $returnUrl,
            'returnURLCancel'      => $returnUrl,
            'returnURLReject'      => $returnUrl,
            'issuer'               => $buildSubject['issuer'] ?? '',
            'additionalParameters' => [
                'service_action_from_magento' => 'verify',
                'initiated_by_magento' => 1,
                'idin_cid' => $this->customerSession->getCustomerId()
            ]
        ];
    }

    /**
     * Retrieves the return URL.
     *
     * @return string
     * @throws LocalizedException
     */
    public function getReturnUrl(): string
    {
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($this->store->getId());
            $url = $url->getRouteUrl('buckaroo/redirect/idinProcess') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    /**
     * Sets the return URL.
     *
     * @param string $url
     * @return $this
     */
    public function setReturnUrl(string $url): IdinDataBuilder
    {
        $routeUrl = $this->urlBuilder->getRouteUrl($url);
        $this->returnUrl = $routeUrl;

        return $this;
    }

    /**
     * Retrieves the form key.
     *
     * @return string
     * @throws LocalizedException
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
