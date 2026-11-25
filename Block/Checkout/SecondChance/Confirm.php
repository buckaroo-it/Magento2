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

namespace Buckaroo\Magento2\Block\Checkout\SecondChance;

use Magento\Framework\View\Element\Template;

/**
 * Page asking the customer to confirm restoring the cart of a payment reminder
 *
 * Rendered only after the controller checked that the link can be used.
 */
class Confirm extends Template
{
    /**
     * Tracking parameters passed on from the reminder link to the checkout
     */
    public const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'];

    /**
     * Token of the reminder link
     *
     * @return string
     */
    public function getToken(): string
    {
        $token = $this->getRequest()->getParam('token');

        return is_string($token) ? $token : '';
    }

    /**
     * Tracking parameters of the reminder link, to submit with the form
     *
     * @return array
     */
    public function getUtmParams(): array
    {
        return array_filter(
            array_intersect_key($this->getRequest()->getParams(), array_flip(self::UTM_PARAMS)),
            'is_string'
        );
    }

    /**
     * URL the form posts to
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->getUrl('buckaroo/checkout/secondchance', ['_nosid' => true]);
    }
}
