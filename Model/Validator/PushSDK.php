<?php

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Exceptions\SDKException;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Magento\Framework\UrlInterface;
use Magento\Framework\Webapi\Rest\Request;
use Buckaroo\Magento2\Logging\Log;

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

    private Log $logging;

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
     * @param $data
     * @return bool
     */
    public function validate($data): bool
    {
        try {
            $post_data = $this->request->getContent();
            $auth_header = $this->request->getHeader('Authorization');
            $uri = $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push');

            return $this->sdkAdapter->validate($post_data, $auth_header, $uri);
        } catch (SDKException $exception) {
            return false;
        }
    }
}
