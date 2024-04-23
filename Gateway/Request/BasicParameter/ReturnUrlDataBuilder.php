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
    protected ?string $returnUrl = null;

    /**
     * @var Order
     */
    protected Order $order;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @var UrlInterface
     */
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
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $returnUrl = $this->getReturnUrl($order);

        return [
            'returnURL' => $returnUrl,
            'returnURLError' => $returnUrl,
            'returnURLCancel' => $returnUrl,
            'returnURLReject' => $returnUrl,
            'pushURL' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push'),
            'pushURLFailure' => $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push')
        ];
    }

    /**
     * Get return url for payment engine
     *
     * @param Order $order
     * @return string|null
     * @throws LocalizedException
     */
    public function getReturnUrl(Order $order): ?string
    {
        if ($this->returnUrl === null) {
            $url = $this->urlBuilder->setScope($order->getStoreId());
            $url = $url->getDirectUrl('buckaroo/redirect/process') . '?form_key=' . $this->getFormKey();

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    /**
     * Set return url
     *
     * @param string $url
     * @return $this
     */
    public function setReturnUrl(string $url): ReturnUrlDataBuilder
    {
        $this->returnUrl = $url;

        return $this;
    }

    /**
     * Get magento form key
     *
     * @return string
     * @throws LocalizedException
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
