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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\StoreId;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
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
     * @param BuckarooAdapter $sdkAdapter
     * @param Request         $request
     * @param PushUrlBuilder  $pushUrlBuilder
     */
    public function __construct(
        BuckarooAdapter $sdkAdapter,
        Request $request,
        PushUrlBuilder $pushUrlBuilder
    ) {
        $this->sdkAdapter = $sdkAdapter;
        $this->request = $request;
        $this->pushUrlBuilder = $pushUrlBuilder;
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

            // The signature covers the URL the gateway called. That is now the store-scoped push
            // URL, but orders placed before this change carry the old storeless one, so both forms
            // have to keep validating.
            foreach ($this->pushUrlBuilder->getCandidateUris($storeId) as $uri) {
                if ($this->sdkAdapter->validate($postData, $authHeader, $uri, $storeId)) {
                    return true;
                }
            }

            return false;
        } catch (BuckarooException $exception) {
            return false;
        }
    }
}
