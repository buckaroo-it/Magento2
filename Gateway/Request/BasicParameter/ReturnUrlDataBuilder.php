<?php

namespace Buckaroo\Magento2\Gateway\Request\BasicParameter;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class ReturnUrlDataBuilder implements BuilderInterface
{
    /**
     * @var null|string
     */
    protected $returnUrl = null;

    /**
     * @var Order
     */
    protected Order $order;

    /** @var FormKey */
    private FormKey $formKey;

    /** @var UrlInterface */
    protected UrlInterface $urlBuilder;


    /**
     * TransactionBuilder constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     */
    public function __construct(
        UrlInterface $urlBuilder,
        FormKey $formKey
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();

        return [
            'returnURL' => $this->getReturnUrl($order),
            'returnURLError' => $this->getReturnUrl($order),
            'returnURLCancel' => $this->getReturnUrl($order),
            'returnURLReject' => $this->getReturnUrl($order),
            'pushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'pushURLFailure' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push')
        ];
    }

    public function getReturnUrl($order)
    {
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($order->getStoreId());
            $url = $url->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    public function setReturnUrl($url)
    {
        $routeUrl = $this->urlBuilder->getRouteUrl($url);
        $this->returnUrl = $routeUrl;

        return $this;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
