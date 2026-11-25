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

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Logging\LogSanitizer;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;

class RequestPushFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Request $request
     */
    private $request;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param ObjectManagerInterface  $objectManager
     * @param Request                 $request
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Request $request,
        BuckarooLoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Create push request
     *
     * @return PushRequestInterface
     */
    public function create(): PushRequestInterface
    {
        try {
            $contentType = $this->request->getContentType();

            if (!empty($contentType)
                && strpos($contentType, 'application/json') !== false
            ) {
                // Read the push from its body, the content the signature is validated over
                $requestData = $this->request->getBodyParams();
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [Factory] | [%s:%s] - Create Json Request Object | request: %s',
                    __METHOD__,
                    __LINE__,
                    LogSanitizer::export($requestData)
                ));

                return $this->objectManager->create(
                    JsonPushRequest::class,
                    ['requestData' => $requestData]
                );
            }
        } catch (\Exception $exception) {
            $this->logger->addDebug(sprintf(
                '[PUSH] | [Factory] | [%s:%s] - Not a JSON request, falling back to HTTP Post handler | Info: %s',
                __METHOD__,
                __LINE__,
                LogSanitizer::singleLine($exception->getMessage())
            ));
        }

        return $this->createFromFormPost();
    }

    /**
     * Create the request for a shopper returning from Buckaroo, which always posts a form
     *
     * @return PushRequestInterface
     */
    public function createFromFormPost(): PushRequestInterface
    {
        $postData = $this->request->getPostValue();
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Factory] | [%s:%s] - Create HTTP Post Request Object | request: %s',
            __METHOD__,
            __LINE__,
            LogSanitizer::export($postData)
        ));

        return $this->objectManager->create(
            HttppostPushRequest::class,
            ['requestData' => $postData]
        );
    }
}
