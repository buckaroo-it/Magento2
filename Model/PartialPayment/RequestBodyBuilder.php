<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\PartialPayment;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Helper\StoreId;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Request fields every partial payment shares
 */
class RequestBodyBuilder
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var PaymentGroupTransaction
     */
    private $groupTransaction;

    /**
     * @var Account
     */
    private $accountConfig;

    /**
     * @var RequestInterface
     */
    private $httpRequest;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var PushUrlBuilder
     */
    private $pushUrlBuilder;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param PaymentGroupTransaction $groupTransaction
     * @param Account                 $accountConfig
     * @param RequestInterface        $httpRequest
     * @param UrlInterface            $urlBuilder
     * @param FormKey                 $formKey
     * @param PushUrlBuilder          $pushUrlBuilder
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        PaymentGroupTransaction $groupTransaction,
        Account $accountConfig,
        RequestInterface $httpRequest,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        PushUrlBuilder $pushUrlBuilder
    ) {
        $this->cartRepository = $cartRepository;
        $this->groupTransaction = $groupTransaction;
        $this->accountConfig = $accountConfig;
        $this->httpRequest = $httpRequest;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->pushUrlBuilder = $pushUrlBuilder;
    }

    /**
     * Shared request body for a partial payment with the given service code
     *
     * The amount is the full grand total in the quote currency. When an earlier partial payment
     * exists, the original transaction key makes the gateway take only what is still open.
     *
     * @param Quote  $quote
     * @param string $serviceCode
     *
     * @return array
     */
    public function build(Quote $quote, string $serviceCode): array
    {
        $incrementId = $this->getIncrementId($quote);
        $storeId = StoreId::normalize($quote->getStoreId());
        $returnUrl = $this->getReturnUrl($storeId);
        $ip = $this->getClientIp($storeId);

        $body = [
            'payment_method'  => $serviceCode,
            'currency'        => $this->getCurrency($quote),
            'amountDebit'     => (float)$quote->getGrandTotal(),
            'invoice'         => $incrementId,
            'order'           => $incrementId,
            'returnURL'       => $returnUrl,
            'returnURLCancel' => $returnUrl,
            'returnURLError'  => $returnUrl,
            'returnURLReject' => $returnUrl,
            'pushURL'         => $this->pushUrlBuilder->getPushUrl($storeId),
            'clientIP'        => [
                'address' => $ip ?? 'unknown',
                'type'    => $ip !== null && strpos($ip, ':') !== false ? '1' : '0',
            ],
        ];

        $originalTransactionKey = $this->groupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);
        if ($originalTransactionKey !== null) {
            $body['originalTransactionKey'] = $originalTransactionKey;
        }

        if ($storeId !== null) {
            // Credentials and live/test mode of the quote's store
            $body['orderStoreId'] = $storeId;
        }

        return $body;
    }

    /**
     * Currency of the amount sent: the quote currency, because the amount is the quote grand total
     *
     * @param Quote $quote
     *
     * @return string|null
     */
    public function getCurrency(Quote $quote): ?string
    {
        $currency = $quote->getCurrency();
        $code = $currency !== null ? $currency->getQuoteCurrencyCode() : null;

        return $code !== null && $code !== '' ? (string)$code : null;
    }

    /**
     * Reserved order id of the quote, reserving one on the first partial payment
     *
     * @param Quote $quote
     *
     * @return string
     */
    private function getIncrementId(Quote $quote): string
    {
        if ($quote->getReservedOrderId() === null) {
            $quote->reserveOrderId();
            $this->cartRepository->save($quote);
        }

        return (string)$quote->getReservedOrderId();
    }

    /**
     * Return URL of the store
     *
     * @param int|null $storeId
     *
     * @return string
     */
    private function getReturnUrl(?int $storeId): string
    {
        return $this->urlBuilder->getRouteUrl('buckaroo/redirect/process', ['_scope' => $storeId])
            . '?form_key=' . $this->formKey->getFormKey();
    }

    /**
     * Shopper IP, read from the IP headers configured for the store when there are any
     *
     * @param int|null $storeId
     *
     * @return string|null
     */
    private function getClientIp(?int $storeId): ?string
    {
        $headers = [];
        $ipHeaders = (string)$this->accountConfig->getIpHeader($storeId);
        if ($ipHeaders !== '') {
            foreach (explode(',', strtoupper($ipHeaders)) as $ipHeader) {
                $headers[] = 'HTTP_' . str_replace('-', '_', trim($ipHeader));
            }
        }

        $ip = (new RemoteAddress($this->httpRequest, $headers))->getRemoteAddress();

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
