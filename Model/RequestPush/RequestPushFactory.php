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

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
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

    private const PII_KEYS = [
        'brq_customer_name', 'brq_billing_firstname', 'brq_billing_lastname', 'brq_billing_email',
        'brq_billing_street', 'brq_billing_phone', 'brq_shipping_firstname', 'brq_shipping_lastname',
        'brq_shipping_email', 'brq_shipping_street', 'brq_shipping_phone', 'brq_card_number',
    ];

    private const PII_JSON_KEYS = ['Customer', 'Billing', 'Shipping'];

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
                $requestData = $this->request->getRequestData();
                $this->logger->addDebug(sprintf(
                    '[PUSH] | [Factory] | [%s:%s] - Create Json Request Object | request: %s',
                    __METHOD__,
                    __LINE__,
                    var_export($this->sanitizeJsonData($requestData), true)
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
                $exception->getMessage()
            ));
        }

        $postData = $this->request->getPostValue();
        $this->logger->addDebug(sprintf(
            '[PUSH] | [Factory] | [%s:%s] - Create HTTP Post Request Object | request: %s',
            __METHOD__,
            __LINE__,
            var_export($this->sanitizePostData($postData), true)
        ));

        return $this->objectManager->create(
            HttppostPushRequest::class,
            ['requestData' => $postData]
        );
    }

    /**
     * Redact PII fields from HTTP POST push data before logging.
     *
     * @param array $data
     * @return array
     */
    private function sanitizePostData(array $data): array
    {
        foreach (self::PII_KEYS as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***REDACTED***';
            }
        }
        return $data;
    }

    /**
     * Redact PII fields from JSON push data before logging.
     *
     * @param array $data
     * @return array
     */
    private function sanitizeJsonData(array $data): array
    {
        foreach (self::PII_JSON_KEYS as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***REDACTED***';
            }
        }

        foreach (['Transaction', 'DataRequest'] as $root) {
            if (isset($data[$root]) && is_array($data[$root])) {
                foreach (self::PII_JSON_KEYS as $key) {
                    if (isset($data[$root][$key])) {
                        $data[$root][$key] = '***REDACTED***';
                    }
                }
            }
        }

        return $data;
    }
}
