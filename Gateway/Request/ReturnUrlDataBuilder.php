<?php

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
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
        FormKey      $formKey
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
    }

    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $payment = $buildSubject['payment'];
        $this->setOrder($payment->getOrder()->getOrder());

        return [
            'returnURL' => $this->getReturnUrl(),
            'returnURLError' => $this->getReturnUrl(),
            'returnURLCancel' => $this->getReturnUrl(),
            'returnURLReject' => $this->getReturnUrl(),
            'pushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'pushURLFailure' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push')
        ];
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
}
