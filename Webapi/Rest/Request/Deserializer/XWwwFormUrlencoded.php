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

namespace Buckaroo\Magento2\Webapi\Rest\Request\Deserializer;

use Magento\Framework\Webapi\Rest\Request\DeserializerInterface;

class XWwwFormUrlencoded implements DeserializerInterface
{
    /**
     * Parse request body into an array of params.
     *
     * @param string $encodedBody Posted content from request.
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>|null
     */
    public function deserialize($encodedBody)
    {
        if (!is_string($encodedBody)) {
            throw new \InvalidArgumentException(
                //phpcs:ignore:Magento2.Functions.DiscouragedFunction
                (string) __('%1 data type is invalid. String is expected.', gettype($encodedBody))
            );
        }

        if ($encodedBody === '') {
            return [];
        }

        $parsedBody = [];
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        parse_str($encodedBody, $parsedBody);

        return $parsedBody;
    }
}
