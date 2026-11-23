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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\StoreId;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Service\Push\RequestUriResolver;
use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Framework\Webapi\Request;

class PushSDK implements ValidatorInterface
{
    /**
     * @var BuckarooAdapter
     */
    private $sdkAdapter;
    /**
     * @var Request $request
     */
    private $request;

    /**
     * @var PushUrlBuilder
     */
    private $pushUrlBuilder;

    /**
     * @var RequestUriResolver
     */
    private $requestUriResolver;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param BuckarooAdapter         $sdkAdapter
     * @param Request                 $request
     * @param PushUrlBuilder          $pushUrlBuilder
     * @param RequestUriResolver      $requestUriResolver
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        BuckarooAdapter $sdkAdapter,
        Request $request,
        PushUrlBuilder $pushUrlBuilder,
        RequestUriResolver $requestUriResolver,
        BuckarooLoggerInterface $logger
    ) {
        $this->sdkAdapter = $sdkAdapter;
        $this->request = $request;
        $this->pushUrlBuilder = $pushUrlBuilder;
        $this->requestUriResolver = $requestUriResolver;
        $this->logger = $logger;
    }

    /**
     * Validate Push SDK
     *
     * $store must be the store of the order this push belongs to. Without it the SDK client is
     * built from the ambient store, which on this REST route is the default store view of the
     * default website — so a multi-store install would verify the signature with the wrong
     * secret key.
     *
     * @param array                                                  $data
     * @param \Magento\Store\Api\Data\StoreInterface|int|string|null $store
     *
     * @throws \Exception
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate($data, $store = null): bool
    {
        try {
            $postData = $this->request->getContent();
            $authHeader = $this->request->getHeader('Authorization');
            $storeId = StoreId::normalize($store);

            // The signature covers the URI the gateway called, so verify against that URI as read
            // from the request first.
            $requestUris = $this->requestUriResolver->getCandidateUris();
            foreach ($requestUris as $uri) {
                if ($this->sdkAdapter->validate($postData, $authHeader, $uri, $storeId)) {
                    return true;
                }
            }

            // Fallback: the push URL as rebuilt from store configuration (store-scoped and the
            // legacy storeless form). Kept for one release to catch request-URI cases not covered
            // above; the log line tells us whether it is still needed before it is removed.
            foreach ($this->pushUrlBuilder->getCandidateUris($storeId) as $uri) {
                if (in_array($uri, $requestUris, true)) {
                    continue;
                }
                if ($this->sdkAdapter->validate($postData, $authHeader, $uri, $storeId)) {
                    $this->logger->addWarning(sprintf(
                        '[PUSH] | [Validator] | [%s:%s] - Push signature matched the rebuilt push URL '
                        . '"%s" but none of the request URIs [%s]',
                        __METHOD__,
                        __LINE__,
                        $uri,
                        implode(', ', $requestUris)
                    ));
                    return true;
                }
            }

            return false;
        } catch (BuckarooException $exception) {
            return false;
        }
    }
}
