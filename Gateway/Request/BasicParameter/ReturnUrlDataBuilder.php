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
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Service\Store\StoreUrlBuilder;
use Laminas\Uri\UriFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class ReturnUrlDataBuilder implements BuilderInterface
{
    public const ADDITIONAL_RETURN_URL = 'buckaroo_return_url';

    /**
     * @var null|string
     */
    protected $returnUrl = null;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var PushUrlBuilder
     */
    private $pushUrlBuilder;

    /**
     * @var StoreUrlBuilder
     */
    private $storeUrlBuilder;

    /**
     * TransactionBuilder constructor.
     *
     * @param UrlInterface    $urlBuilder
     * @param FormKey         $formKey
     * @param PushUrlBuilder  $pushUrlBuilder
     * @param StoreUrlBuilder $storeUrlBuilder
     */
    public function __construct(
        UrlInterface $urlBuilder,
        FormKey $formKey,
        PushUrlBuilder $pushUrlBuilder,
        StoreUrlBuilder $storeUrlBuilder
    ) {
        $this->pushUrlBuilder = $pushUrlBuilder;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->storeUrlBuilder = $storeUrlBuilder;
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder()->getOrder();
        $returnUrl = $this->getReturnUrl($order);
        $pushUrl = $this->pushUrlBuilder->getPushUrl($order->getStoreId());

        return [
            'returnURL' => $returnUrl,
            'returnURLError' => $returnUrl,
            'returnURLCancel' => $returnUrl,
            'returnURLReject' => $returnUrl,
            'pushURL' => $pushUrl,
            'pushURLFailure' => $pushUrl
        ];
    }

    /**
     * Get return url for payment engine
     *
     * @param Order $order
     *
     * @throws LocalizedException
     *
     * @return string|null
     */
    public function getReturnUrl(Order $order): ?string
    {
        $returnUrl = $this->getReturnUrlFromPayment($order);
        if ($returnUrl !== null) {
            $this->setReturnUrl($returnUrl);
            return $this->returnUrl;
        }

        if ($this->returnUrl === null) {
            // Built from the order store's OWN base URL, not from getDirectUrl()'s ambient one.
            // During a frontend placeOrder the two are the same, but a PayPerEmail or PayLink order
            // is created in the admin, where the ambient store is the admin's - and ['_scope' => id]
            // does not change the host getDirectUrl() resolves. On a per-domain setup that returned
            // the shopper to the default store's domain, where their session does not exist.
            $url = $this->storeUrlBuilder->getUrl($order->getStoreId(), 'buckaroo/redirect/process')
                . '?form_key=' . $this->getFormKey() . $this->getStoreParam($order);

            $this->setReturnUrl($url);
        }

        return $this->returnUrl;
    }

    /**
     * Set return url
     *
     * @param string $url
     *
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
     * @throws LocalizedException
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get the custom return URL stored on the payment, if it is a valid http(s) URL.
     *
     * @param Order $order
     * @return string|null
     */
    public function getReturnUrlFromPayment(Order $order): ?string
    {
        if ($order->getPayment() === null ||
            $order->getPayment()->getAdditionalInformation(self::ADDITIONAL_RETURN_URL) === null
        ) {
            return null;
        }
        $returnUrl = (string)$order->getPayment()->getAdditionalInformation(self::ADDITIONAL_RETURN_URL);
        if (filter_var($returnUrl, FILTER_VALIDATE_URL) !== false) {
            try {
                $scheme = UriFactory::factory($returnUrl)->getScheme();
            } catch (\InvalidArgumentException $e) {
                return null;
            }

            if (in_array($scheme, ['http', 'https'])) {
                return $returnUrl;
            }
        }

        return null;
    }

    /**
     * Get the ___store parameter that pins the return to the order's store view.
     *
     * The gateway returns the shopper with a cross-site POST, so the SameSite=Lax store cookie is
     * not sent and Magento resolves the default store for that request. Everything the redirect
     * controller then does - including building the URL it sends the shopper to - runs in the wrong
     * scope. On a setup where each website has its own domain that means being dumped on another
     * store's checkout, where the cart does not exist.
     *
     * Naming the store in the URL removes the dependency on a cookie that cannot survive the trip.
     *
     * @param Order $order
     *
     * @return string
     */
    private function getStoreParam(Order $order): string
    {
        try {
            $code = (string)$order->getStore()->getCode();
        } catch (\Exception $exception) {
            return '';
        }

        return $code === '' ? '' : '&' . StoreManagerInterface::PARAM_NAME . '=' . urlencode($code);
    }
}
