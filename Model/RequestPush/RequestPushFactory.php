<?php

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;

class RequestPushFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var Request $request
     */
    private $request;
    private Log $logging;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Request $request
     */
    public function __construct(ObjectManagerInterface $objectManager, Request $request, Log $logging)
    {
        $this->_objectManager = $objectManager;
        $this->request = $request;
        $this->logging = $logging;
    }

    /**
     * Create push request
     *
     * @return PushRequestInterface
     */
    public function create(): PushRequestInterface
    {
        try {
            if (strpos($this->request->getContentType(), 'application/json') !== false) {
                $this->logging->addDebug(__METHOD__ . '|Create json object|' . var_export($this->request->getRequestData(), true));
                return $this->_objectManager->create(
                    \Buckaroo\Magento2\Model\RequestPush\JsonPushRequest::class,
                    ['requestData' => $this->request->getRequestData()]);
            }
        } catch (\Exception $exception) {
            $this->logging->addDebug(__METHOD__ . '|EXCEPTION|' . var_export($exception->getMessage(), true));
        }

        $this->logging->addDebug(__METHOD__ . '|Create httppost object|' . var_export($this->request->getRequestData(), true));
        return $this->_objectManager->create(
            \Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest::class,
            ['requestData' => $this->request->getPostValue()]);

    }
}
