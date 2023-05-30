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

use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Request;

class PushSDK implements ValidatorInterface
{
    /** @var UrlInterface */
    protected UrlInterface $urlBuilder;
    /**
     * @var BuckarooAdapter
     */
    private BuckarooAdapter $sdkAdapter;
    /**
     * @var Request $request
     */
    private Request $request;

    /**
     * @param BuckarooAdapter $sdkAdapter
     * @param Request $request
     * @param UrlInterface $urlBuilder
     */
    public function __construct(BuckarooAdapter $sdkAdapter, Request $request, UrlInterface $urlBuilder)
    {
        $this->sdkAdapter = $sdkAdapter;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Validate Push SDK
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate($data): bool
    {
        try {
            $postData = $this->request->getContent();
            $authHeader = $this->request->getHeader('Authorization');
            $uri = $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push');

            return $this->sdkAdapter->validate($postData, $authHeader, $uri);
        } catch (BuckarooException $exception) {
            return false;
        }
    }
}
