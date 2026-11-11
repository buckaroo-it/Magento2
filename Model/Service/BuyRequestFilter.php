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
