<?php

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Exceptions\BuckarooException;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Request;

class PushSDK implements ValidatorInterface
{
    /**
     * @var BuckarooAdapter
     */
    private BuckarooAdapter $sdkAdapter;

    /**
     * @var Request $request
     */
    private $request;

    /** @var UrlInterface */
    protected UrlInterface $urlBuilder;

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
