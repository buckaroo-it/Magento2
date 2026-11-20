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

namespace Buckaroo\Magento2\Helper;

/**
 * Turn text into a JavaScript string literal for inline admin handlers such as confirmSetLocation(...)
 *
 * Magento renders a button's onclick inside a <script> block, so a translated message with an apostrophe or a
 * quote must not end the string, and "<" / ">" must not end the script.
 */
class JsString
{
    /**
     * Encode a value as a JavaScript string literal
     *
     * @param mixed $value text or a Phrase
     * @return string a double-quoted literal with no raw quote, apostrophe or angle bracket inside
     */
    // phpcs:ignore Magento2.Functions.StaticFunction -- pure stateless encoder, interception has no value
    public static function encode($value): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return (string)json_encode((string)$value, $flags);
    }
}
