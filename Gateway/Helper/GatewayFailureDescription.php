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

namespace Buckaroo\Magento2\Gateway\Helper;

// phpcs:disable Magento2.Functions.StaticFunction -- pure stateless predicate shared by the validator and the gateway command
/**
 * Decides whether a gateway failure description carries a reason a shopper can act on.
 */
class GatewayFailureDescription
{
    /**
     * Shopper-facing text used when the gateway gives no usable reason.
     */
    public const STANDARD_DECLINE_MESSAGE = 'Transaction has been declined. Please try again later.';

    /**
     * True when the description holds no reason at all.
     *
     * Three shapes come back from Plaza with an empty payload:
     *  - an empty string;
     *  - punctuation only, e.g. the Billink "ErrorResponseMessage": "." parameter;
     *  - a sentence template whose reason was never filled in, e.g. SubCode S996
     *    "An error occurred while processing the transaction: ." — the words in
     *    front of the colon are Plaza boilerplate, the reason sits behind it.
     *
     * @param string|null $description
     *
     * @return bool
     */
    public static function isUninformative(?string $description): bool
    {
        $description = trim((string)$description);

        if (self::hasNoWords($description)) {
            return true;
        }

        $lastColon = strrpos($description, ':');

        return $lastColon !== false && self::hasNoWords(substr($description, $lastColon + 1));
    }

    /**
     * True when the text contains no letters or digits, only whitespace and punctuation.
     *
     * @param string $text
     *
     * @return bool
     */
    private static function hasNoWords(string $text): bool
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $text) === '';
    }
}
