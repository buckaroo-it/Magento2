<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;

class IdinDataBuilder implements BuilderInterface
{
    /**
     * @var CustomerSession
     */
    private $customerSession;
    /**
     * @var null|string
     */
    private $returnUrl = null;
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
    /**
     * @var \Magento\Store\Api\Data\StoreInterface
     */
    protected $store;
    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @param CustomerSession $customerSession
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param StoreManagerInterface $storeManager
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function __construct(
        CustomerSession $customerSession,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        StoreManagerInterface $storeManager
    ) {
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->store = $storeManager->getStore();
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject): array
    {
        $returnUrl = $this->getReturnUrl();

        return [
            'payment_method' => 'idin',
            'returnURL' => $returnUrl,
            'returnURLError' => $returnUrl,
            'returnURLCancel' => $returnUrl,
            'returnURLReject' => $returnUrl,
            'issuer' => $buildSubject['issuer'],
            'additionalParameters' => [
                'idin_cid' => $this->customerSession->getCustomerId()
            ]];
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
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
