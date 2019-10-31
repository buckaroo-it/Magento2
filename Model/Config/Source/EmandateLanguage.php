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

namespace TIG\Buckaroo\Model\Config\Source;

class EmandateLanguage implements \Magento\Framework\Option\ArrayInterface
{
    const EMANDATE_LANGUAGE_DUTCH = 'nl_NL';
    const EMANDATE_LANGUAGE_ENGLISH = 'en_US';

    public function toOptionArray()
    {
        $options = [];

        $options[] = ['value' => self::EMANDATE_LANGUAGE_DUTCH, 'label' => __('Dutch')];
        $options[] = ['value' => self::EMANDATE_LANGUAGE_ENGLISH, 'label' => __('English')];

        return $options;
    }
}
