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
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Request;

class PushSDK implements ValidatorInterface
{
    /** @var UrlInterface */
    protected $urlBuilder;
    /**
     * @var BuckarooAdapter
     */
    private $sdkAdapter;
    /**
     * @var Request $request
     */
    private $request;

    /**
     * @param BuckarooAdapter $sdkAdapter
     * @param Request         $request
     * @param UrlInterface    $urlBuilder
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
     *
     * @throws \Exception
     *
     * @return bool
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
