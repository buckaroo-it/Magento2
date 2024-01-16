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

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;

class RequestPushFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var Request $request
     */
    private Request $request;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Request $request
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
            if (strpos($this->request->getContentType(), 'application/json') !== false) {
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [Factory] | [%s:%s] - Create Json Request Object | request: %s',
                    __METHOD__,
                    __LINE__,
                    var_export($this->request->getRequestData(), true)
                ));

                return $this->objectManager->create(
                    JsonPushRequest::class,
                    ['requestData' => $this->request->getRequestData()]
                );
            }
        } catch (\Exception $exception) {
            $this->logger->addError(sprintf(
                '[PUSH] | [Factory] | [%s:%s] - Create Json Request Object | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $exception->getMessage()
            ));
        }

        $this->logger->addDebug(sprintf(
            '[PUSH] | [Factory] | [%s:%s] - Create HTTP Post Request Object | request: %s',
            __METHOD__,
            __LINE__,
            var_export($this->request->getRequestData(), true)
        ));

        return $this->objectManager->create(
            HttppostPushRequest::class,
            ['requestData' => $this->request->getPostValue()]
        );
    }
}
