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
