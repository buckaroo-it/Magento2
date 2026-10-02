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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Adapter\BuckarooAdapter;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Store\Api\Data\StoreInterface;

class Push implements ValidatorInterface
{
    /**
     * @var Data
     */
    public $helper;

    /**
     * @var BuckarooLoggerInterface
     */
    public $logger;

    /**
     * @var BuckarooAdapter
     */
    private $sdkAdapter;

    /**
     * @var string[]
     */
    public $bpeResponseMessages = [
        190 => 'Success',
        490 => 'Payment failure',
        491 => 'Validation error',
        492 => 'Technical error',
        690 => 'Payment rejected',
        790 => 'Waiting for user input',
        791 => 'Waiting for processor',
        792 => 'Waiting on consumer action',
        793 => 'Payment on hold',
        890 => 'Cancelled by consumer',
        891 => 'Cancelled by merchant'
    ];

    /**
     * @param Data                    $helper
     * @param BuckarooLoggerInterface $logger
     * @param BuckarooAdapter         $sdkAdapter
     */
    public function __construct(
        Data $helper,
        BuckarooLoggerInterface $logger,
        BuckarooAdapter $sdkAdapter
    ) {
        $this->helper     = $helper;
        $this->logger     = $logger;
        $this->sdkAdapter = $sdkAdapter;
    }

    /**
     * Validate push — actual validation is performed via validateSignature() through HttppostPushRequest::validate().
     *
     * This method exists to satisfy ValidatorInterface but must not be called directly.
     *
     * @param array|object $data
     *
     * @return bool
     */
    public function validate($data): bool
    {
        throw new \LogicException('Call validateSignature() directly or use HttppostPushRequest::validate().');
    }

    /**
     * Checks if the status code is returned by the bpe push and is valid.
     *
     * @param int|string $code
     *
     * @return array
     */
    public function validateStatusCode($code): array
    {
        if (null !== $this->helper->getStatusByValue($code)
            && isset($this->bpeResponseMessages[$code])
        ) {
            return [
                'message' => $this->bpeResponseMessages[$code],
                'status'  => $this->helper->getStatusByValue($code),
                'code'    => $code,
            ];
        } else {
            return [
                'message' => 'Onbekende responsecode: ' . $code,
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL',
                'code'    => $code,
            ];
        }
    }

    /**
     * Verify the HTTP-post push signature.
     *
     * @param array                          $originalPostData Raw push data, original key casing preserved
     * @param array                          $postData         Case-folded push data (used only for the fast-fail check)
     * @param int|string|StoreInterface|null $store
     *
     * @return bool
     */
    public function validateSignature(array $originalPostData, array $postData, $store = null): bool
    {
        if (!isset($postData['brq_signature'])) {
            return false;
        }

        try {
            return $this->sdkAdapter->validate($originalPostData, null, null, $this->resolveStoreId($store));
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[PUSH] | [Webapi] | [%s:%s] - Signature validation failed: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Normalise the store argument to a store id for the SDK adapter.
     *
     * @param int|string|StoreInterface|null $store
     *
     * @return int|null
     */
    private function resolveStoreId($store): ?int
    {
        if ($store instanceof StoreInterface) {
            return (int)$store->getId();
        }

        if (is_numeric($store)) {
            return (int)$store;
        }

        return null;
    }
}
