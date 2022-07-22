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
     * @param Log $logging
     */
    public function __construct(BuckarooAdapter $sdkAdapter, Request $request, UrlInterface $urlBuilder, Log $logging)
    {
        $this->sdkAdapter = $sdkAdapter;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->logging = $logging;
    }

    /**
     * @param $data
     * @return bool
     */
    public function validate($data) : bool
    {
        try {
            $post_data = $this->request->getContent();
            $this->logging->addDebug(__METHOD__ . '|POST_DATA_VALIDATOR|' . var_export($post_data, true));
            $auth_header = $this->request->getHeader('Authorization');
            $this->logging->addDebug(__METHOD__ . '|Authorization|' . var_export($auth_header, true));
            $uri = $this->urlBuilder->getDirectUrl('rest/V1/buckaroo/push');
            $this->logging->addDebug(__METHOD__ . '|URL|' . var_export($uri, true));

            return $this->sdkAdapter->validate($post_data, $auth_header, $uri);
        } catch (SDKException $exception) {
            return false;
        }
    }
}
