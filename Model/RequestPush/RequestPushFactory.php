<?php

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\PushRequestInterface;
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

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Request $request
     */
    public function __construct(ObjectManagerInterface $objectManager, Request $request)
    {
        $this->_objectManager = $objectManager;
        $this->request = $request;
    }

    /**
     * Create push request
     *
     * @return PushRequestInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function create(): PushRequestInterface
    {
        try {
            if (strpos($this->request->getContentType(), 'application/json') !== false) {
                return $this->_objectManager->create(
                    \Buckaroo\Magento2\Model\RequestPush\JsonPushRequest::class,
                    ['requestData' => $this->request->getRequestData()]);
            }
        } catch (\Exception $exception) {

        }

        return $this->_objectManager->create(
            \Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest::class,
            ['requestData' => $this->request->getPostValue()]);

    }
}
