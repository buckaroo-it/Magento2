<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Service;

use Laminas\Stdlib\Parameters;

/**
 * Turns the serialized product page form into a buy request that only carries
 * the product selection fields. Extra fields can be allowed through di.xml.
 */
class BuyRequestFilter
{
    /**
     * Product form fields used to select a product, its options and quantity.
     */
    private const DEFAULT_ALLOWED_KEYS = [
        'product',
        'qty',
        'item',
        'related_product',
        'selected_configurable_option',
        'super_attribute',
        'super_group',
        'options',
        'bundle_option',
        'bundle_option_qty',
        'links',
        'giftcard_amount',
        'custom_giftcard_amount',
        'giftcard_sender_name',
        'giftcard_sender_email',
        'giftcard_recipient_name',
        'giftcard_recipient_email',
        'giftcard_message',
    ];

    /**
     * @var string[]
     */
    private $allowedKeys;

    /**
     * @param string[] $additionalAllowedKeys
     */
    public function __construct(array $additionalAllowedKeys = [])
    {
        $this->allowedKeys = array_values(array_unique(array_merge(
            self::DEFAULT_ALLOWED_KEYS,
            array_filter($additionalAllowedKeys, 'is_string')
        )));
    }

    /**
     * Parse a serialized form string and keep only the allowed fields
     *
     * @param string $formData
     *
     * @return array
     */
    public function fromFormString(string $formData): array
    {
        $parameters = new Parameters();
        $parameters->fromString($formData);

        return $this->filter($parameters->toArray());
    }

    /**
     * Keep only the allowed top-level fields
     *
     * @param array $data
     *
     * @return array
     */
    public function filter(array $data): array
    {
        return array_intersect_key($data, array_flip($this->allowedKeys));
    }
}
